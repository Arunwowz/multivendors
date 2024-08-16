<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobImport;

use Exception;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Filesystem\MissingFileException;
use WPStaging\Framework\Queue\FinishedQueueException;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Exceptions\DiskNotWritableException;
use WPStaging\Pro\Backup\Task\ImportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\Entity\BackupMetadata;
use WPStaging\Pro\Backup\Service\BackupsFinder;
use WPStaging\Pro\Backup\Service\Extractor;

class ExtractFilesTask extends ImportTask
{
    /** @var Extractor */
    protected $extractorService;

    protected $start;

    /** @var int */
    protected $totalFiles;

    /** @var BackupMetadata */
    protected $metadata;

    /** @var int */
    protected $totalFilesPart;

    /** @var int */
    protected $filesExtractionIndex;

    public function __construct(Extractor $extractor, LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);
        $this->extractorService = $extractor;
    }

    public static function getTaskName()
    {
        return 'backup_restore_extract';
    }

    public static function getTaskTitle()
    {
        return 'Extracting Files';
    }

    public function execute()
    {
        try {
            $this->prepareExtraction();
        } catch (MissingFileException $ex) {
            $this->jobDataDto->setFilePartIndex($this->jobDataDto->getFilePartIndex() + 1);
            $this->jobDataDto->setExtractorFilesExtracted(0);
            $this->jobDataDto->setExtractorMetadataIndexPosition(0);
            return $this->generateResponse(false);
        }

        $this->start = microtime(true);

        try {
            $this->extractorService->extract();
        } catch (DiskNotWritableException $e) {
            // No-op, just stop execution
        } catch (FinishedQueueException $e) {
            if ($this->jobDataDto->getExtractorFilesExtracted() !== $this->stepsDto->getTotal()) {
                // Unexpected finish. Log the difference and continue.
                $this->logger->warning(sprintf('Expected to find %d files in Backup, but found %d files instead.', $this->stepsDto->getTotal(), $this->jobDataDto->getExtractorFilesExtracted()));
                // Force the completion to avoid a loop.
                $this->jobDataDto->setExtractorFilesExtracted($this->stepsDto->getTotal());
            }
        }

        $this->stepsDto->setCurrent($this->jobDataDto->getExtractorFilesExtracted());

        $this->logger->info(sprintf('Extracted %d/%d files (%s)', $this->stepsDto->getCurrent(), $this->stepsDto->getTotal(), $this->getExtractSpeed()));

        if (!$this->stepsDto->isFinished()) {
            return $this->generateResponse(false);
        }

        if (!$this->metadata->getIsMultipartBackup() && $this->metadata->getIsExportingUploads()) {
            $this->logger->info(__('Restored Media Library', 'wp-staging'));
            return $this->generateResponse(false);
        }

        if (!$this->metadata->getIsMultipartBackup()) {
            return $this->generateResponse(false);
        }

        $this->filesExtractionIndex++;
        $this->jobDataDto->setFilePartIndex($this->filesExtractionIndex);
        $this->jobDataDto->setExtractorFilesExtracted(0);
        $this->jobDataDto->setExtractorMetadataIndexPosition(0);
        if ($this->filesExtractionIndex === $this->totalFilesPart && $this->metadata->getIsExportingUploads()) {
            $this->logger->info(__('Restored Media Library', 'wp-staging'));
        }

        return $this->generateResponse(false);
    }

    protected function getExtractSpeed()
    {
        $elapsed = microtime(true) - $this->start;
        $bytesPerSecond = min(10 * GB_IN_BYTES, absint($this->extractorService->getBytesWrittenInThisRequest() / $elapsed));

        if ($bytesPerSecond === 10 * GB_IN_BYTES) {
            return '10GB/s+';
        }

        return size_format($bytesPerSecond) . '/s';
    }

    protected function prepareExtraction()
    {
        $this->metadata = $this->jobDataDto->getBackupMetadata();
        $this->totalFiles = $this->metadata->getTotalFiles();
        if (!$this->metadata->getIsMultipartBackup()) {
            $this->stepsDto->setTotal($this->totalFiles);
            $this->extractorService->inject($this->jobDataDto, $this->logger);
            $this->extractorService->setFileToExtract($this->jobDataDto->getFile());
            return;
        }

        $this->filesExtractionIndex = $this->jobDataDto->getFilePartIndex();

        $filesPart = $this->metadata->getMultipartMetadata()->getFileParts();
        $this->totalFilesPart = count($filesPart);
        $backupPart = $filesPart[$this->filesExtractionIndex];

        $backupsDirectory = WPStaging::make(BackupsFinder::class)->getBackupsDirectory();
        $partMetadata = new BackupMetadata();

        $fileToExtract = $backupsDirectory . $backupPart;

        if (!file_exists($fileToExtract)) {
            $this->logger->warning(sprintf(esc_html__('Backup part %s doesn\'t exist. Skipping from extraction', 'wp-staging'), basename($fileToExtract)));
            throw new MissingFileException();
        }

        $partMetadata = $partMetadata->hydrateByFilePath($fileToExtract);
        $this->stepsDto->setTotal($partMetadata->getMultipartMetadata()->getTotalFiles());
        $this->extractorService->inject($this->jobDataDto, $this->logger);
        $this->extractorService->setFileToExtract($fileToExtract);
    }
}
