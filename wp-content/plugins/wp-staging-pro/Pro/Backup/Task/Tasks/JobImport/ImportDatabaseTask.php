<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobImport;

use WPStaging\Framework\Filesystem\MissingFileException;
use WPStaging\Framework\Filesystem\PathIdentifier;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Service\Database\DatabaseImporter;
use WPStaging\Pro\Backup\Service\Database\Importer\DatabaseSearchReplacer;
use WPStaging\Pro\Backup\Task\ImportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;
use WPStaging\Framework\Utils\Cache\Cache;

class ImportDatabaseTask extends ImportTask
{
    /** @var DatabaseImporter */
    private $databaseImport;

    /** @var PathIdentifier */
    private $pathIdentifier;

    /** @var DatabaseSearchReplacer */
    private $databaseSearchReplacer;

    public function __construct(DatabaseImporter $databaseImport, LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue, PathIdentifier $pathIdentifier, DatabaseSearchReplacer $databaseSearchReplacer)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);
        $this->databaseImport = $databaseImport;
        $this->databaseImport->setup($this->logger, $stepsDto, $this);
        $this->pathIdentifier = $pathIdentifier;
        $this->databaseSearchReplacer = $databaseSearchReplacer;
    }

    public static function getTaskName()
    {
        return 'backup_restore_database';
    }

    public static function getTaskTitle()
    {
        return 'Restoring Database';
    }

    public function execute()
    {
        if ($this->jobDataDto->getIsMissingDatabaseFile()) {
            $partIndex = $this->jobDataDto->getDatabasePartIndex();
            $this->jobDataDto->setDatabasePartIndex($partIndex + 1);
            $this->logger->warning(sprintf('Skip importing reset of database part: %d.', $partIndex));
            return $this->generateResponse(false);
        }

        try {
            $this->prepare();
        } catch (MissingFileException $e) {
            return $this->generateResponse(false);
        }

        $start = microtime(true);
        $before = $this->stepsDto->getCurrent();

        $this->databaseImport->import($this->jobDataDto->getTmpDatabasePrefix());

        $perSecond = ($this->stepsDto->getCurrent() - $before) / (microtime(true) - $start);
        $this->logger->info(sprintf('Executed %s/%s queries (%s queries per second)', number_format_i18n($this->stepsDto->getCurrent()), number_format_i18n($this->stepsDto->getTotal()), number_format_i18n((int)$perSecond)));

        if ($this->stepsDto->isFinished() && $this->jobDataDto->getBackupMetadata()->getIsMultipartBackup()) {
            $this->jobDataDto->setDatabasePartIndex($this->jobDataDto->getDatabasePartIndex() + 1);
            $this->stepsDto->setCurrent(0);
            // to make sure finish condition work
            $this->stepsDto->setTotal(0);
        }

        return $this->generateResponse(false);
    }

    /**
     * @see \WPStaging\Pro\Backup\Service\Database\Exporter\RowsExporter::setupExportSearchReplace For Exporter Search/Replace.
     */
    public function prepare()
    {
        $metadata = $this->jobDataDto->getBackupMetadata();
        $databaseFile = '';
        $databasePartIndex = 0;
        $isSplitBackup = $metadata->getIsMultipartBackup();
        if ($isSplitBackup) {
            $databasePartIndex = $this->jobDataDto->getDatabasePartIndex();
            $databasePart = $metadata->getMultipartMetadata()->getDatabaseParts()[$databasePartIndex];
            $databaseFile = $this->pathIdentifier->getBackupDirectory() . $databasePart;
        } else {
            $databaseFile = $this->pathIdentifier->transformIdentifiableToPath($metadata->getDatabaseFile());
        }

        if (!file_exists($databaseFile) && $isSplitBackup) {
            $this->jobDataDto->setDatabasePartIndex($databasePartIndex + 1);
            $this->jobDataDto->setIsMissingDatabaseFile(true);
            $this->logger->warning(sprintf('Skip importing database. Missing Part Index: %d.', $databasePartIndex));

            throw new MissingFileException();
        }

        if (!file_exists($databaseFile)) {
            throw new \RuntimeException(__('Could not find database file to import.', 'wp-staging'));
        }

        $this->databaseImport->setFile($databaseFile);
        $this->databaseImport->seekLine($this->stepsDto->getCurrent());

        if (!$this->stepsDto->getTotal()) {
            $this->stepsDto->setTotal($this->databaseImport->getTotalLines());
            if ($isSplitBackup && $databasePartIndex !== 0) {
                $this->logger->info(sprintf('Importing Database File Part Index: %d', $databasePartIndex));
            }
        }

        $this->databaseImport->setSearchReplace($this->databaseSearchReplacer->getSearchAndReplace($this->jobDataDto, get_site_url(), get_home_url()));
    }
}
