<?php

namespace WPStaging\Pro\Backup\Service;

use Exception;
use WPStaging\Framework\Adapter\Directory;
use WPStaging\Framework\Filesystem\FileObject;
use WPStaging\Framework\Filesystem\DiskWriteCheck;
use WPStaging\Framework\Filesystem\MissingFileException;
use WPStaging\Framework\Filesystem\PathIdentifier;
use WPStaging\Framework\Queue\FinishedQueueException;
use WPStaging\Framework\Traits\ResourceTrait;
use WPStaging\Pro\Backup\Dto\Job\JobImportDataDto;
use WPStaging\Pro\Backup\Entity\BackupMetadata;
use WPStaging\Pro\Backup\Entity\FileBeingExtracted;
use WPStaging\Pro\Backup\Exceptions\DiskNotWritableException;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

use function WPStaging\functions\debug_log;

class Extractor
{
    use ResourceTrait;

    /** @var JobImportDataDto */
    private $jobImportDataDto;

    /**
     * File currently being extracted
     * @var FileBeingExtracted|null
     */
    private $extractingFile;

    /** @var FileObject */
    private $wpstgFile;

    /** @var string */
    private $dirImport;

    /** @var int */
    private $wpstgIndexOffsetForCurrentFile;

    /** @var int */
    private $wpstgIndexOffsetForNextFile;

    /** @var LoggerInterface */
    private $logger;

    /** @var int How many bytes were written in this request. */
    protected $bytesWrittenThisRequest = 0;

    /** @var PathIdentifier */
    protected $pathIdentifier;

    /** @var Directory */
    protected $directory;

    /** @var diskWriteCheck */
    protected $diskWriteCheck;

    public function __construct(PathIdentifier $pathIdentifier, Directory $directory, DiskWriteCheck $diskWriteCheck)
    {
        $this->pathIdentifier = $pathIdentifier;
        $this->directory = $directory;
        $this->diskWriteCheck = $diskWriteCheck;
    }

    public function inject(JobImportDataDto $jobImportDataDto, LoggerInterface $logger)
    {
        $this->jobImportDataDto = $jobImportDataDto;
        $this->wpstgFile = new FileObject($this->jobImportDataDto->getFile());
        $this->dirImport = $this->jobImportDataDto->getTmpDirectory();
        $this->logger = $logger;
    }

    /**
     * @return JobImportDataDto
     * @throws DiskNotWritableException
     */
    public function extract()
    {
        while (!$this->isThreshold()) {
            try {
                $this->findFileToExtract();
            } catch (FinishedQueueException $e) {
                // Explicit re-throw for readability
                throw $e;
            } catch (\OutOfRangeException $e) {
                // Done processing, or failed
                return $this->jobImportDataDto;
            } catch (\RuntimeException $e) {
                $this->logger->warning($e->getMessage());
                continue;
            } catch (MissingFileException $e) {
                continue;
            }

            $this->extractCurrentFile();
        }

        return $this->jobImportDataDto;
    }

    private function findFileToExtract()
    {
        if ($this->jobImportDataDto->getExtractorMetadataIndexPosition() === 0) {
            $this->jobImportDataDto->setExtractorMetadataIndexPosition($this->jobImportDataDto->getCurrentFileHeaderStart());
        }

        $this->wpstgFile->fseek($this->jobImportDataDto->getExtractorMetadataIndexPosition());

        // Store the index position when reading the current file
        $this->wpstgIndexOffsetForCurrentFile = $this->wpstgFile->ftell();

        // e.g: wp-content/themes/twentytwentyone/readme.txt|9378469:4491
        $rawIndexFile = $this->wpstgFile->readAndMoveNext();

        // Store the index position of the next file to be processed
        $this->wpstgIndexOffsetForNextFile = $this->wpstgFile->ftell();

        if (strpos($rawIndexFile, '|') === false || strpos($rawIndexFile, ':') === false) {
            throw new FinishedQueueException();
        }

        // ['{T}twentytwentyone/readme.txt', '9378469:4491']
        list($identifiablePath, $indexPosition) = explode('|', trim($rawIndexFile));

        // ['9378469', '4491']
        list($offsetStart, $length) = explode(':', trim($indexPosition));

        $identifier = $this->pathIdentifier->getIdentifierFromPath($identifiablePath);

        if ($identifier === PathIdentifier::IDENTIFIER_UPLOADS) {
            $extractFolder = $this->directory->getUploadsDirectory();
        } else {
            $extractFolder = $this->dirImport . $identifier;
        }

        if (!wp_mkdir_p($extractFolder)) {
            throw new \RuntimeException("Could not create folder to extract backup file: $extractFolder");
        }

        $this->extractingFile = new FileBeingExtracted($identifiablePath, $extractFolder, $offsetStart, $length, $this->pathIdentifier);
        $this->extractingFile->setWrittenBytes($this->jobImportDataDto->getExtractorFileWrittenBytes());

        if ($identifier === PathIdentifier::IDENTIFIER_UPLOADS && $this->extractingFile->getWrittenBytes() === 0) {
            if (file_exists($this->extractingFile->getExportPath())) {
                // Delete the original upload file
                if (!unlink($this->extractingFile->getExportPath())) {
                    throw new \RuntimeException(__(sprintf('Could not delete original media library file %s. Skipping exporting it...', 'wp-staging'), $this->extractingFile->getRelativePath()));
                }
            }
        }

        $this->wpstgFile->fseek($this->extractingFile->getStart() + $this->jobImportDataDto->getExtractorFileWrittenBytes());
    }

    public function getBytesWrittenInThisRequest()
    {
        return $this->bytesWrittenThisRequest;
    }

    public function setFileToExtract($filePath)
    {
        try {
            $this->wpstgFile = new FileObject($filePath);
            $metadata = new BackupMetadata();
            $metadata = $metadata->hydrateByFile($this->wpstgFile);
            $this->jobImportDataDto->setCurrentFileHeaderStart($metadata->getHeaderStart());
        } catch (Exception $ex) {
            throw new MissingFileException(sprintf("Following backup part missing: %s", $filePath));
        }
    }

    /**
     * @throws DiskNotWritableException
     */
    private function extractCurrentFile()
    {
        try {
            if ($this->isThreshold()) {
                // Prevent considering a file as big just because we start extracting at the threshold
                return;
            }

            $this->fileBatchWrite();

            $this->bytesWrittenThisRequest += $this->extractingFile->getWrittenBytes();

            if (!$this->extractingFile->isFinished()) {
                if ($this->extractingFile->getWrittenBytes() > 0 && $this->extractingFile->getTotalBytes() > 10 * MB_IN_BYTES) {
                    $percentProcessed = ceil(($this->extractingFile->getWrittenBytes() / $this->extractingFile->getTotalBytes()) * 100);
                    $this->logger->info(sprintf('Extracting big file: %s - %s/%s (%s%%)', $this->extractingFile->getRelativePath(), size_format($this->extractingFile->getWrittenBytes(), 2), size_format($this->extractingFile->getTotalBytes(), 2), $percentProcessed));
                }

                $this->jobImportDataDto->setExtractorMetadataIndexPosition($this->wpstgIndexOffsetForCurrentFile);
                $this->jobImportDataDto->setExtractorFileWrittenBytes($this->extractingFile->getWrittenBytes());

                return;
            }
        } catch (DiskNotWritableException $e) {
            // Re-throw
            throw $e;
        } catch (\OutOfRangeException $e) {
            // Backup header, should be ignored silently
            $this->extractingFile->setWrittenBytes($this->extractingFile->getTotalBytes());
        } catch (Exception $e) {
            // Set this file as "written", so that we can skip to the next file.
            $this->extractingFile->setWrittenBytes($this->extractingFile->getTotalBytes());

            if (defined('WPSTG_DEBUG') && WPSTG_DEBUG) {
                $this->logger->warning(sprintf('Skipped file %s. Reason: %s', $this->extractingFile->getRelativePath(), $e->getMessage()));
            }
        }

        // Jump to the next file of the index
        $this->jobImportDataDto->setExtractorMetadataIndexPosition($this->wpstgIndexOffsetForNextFile);

        $this->jobImportDataDto->incrementExtractorFilesExtracted();

        // Reset offset pointer
        $this->jobImportDataDto->setExtractorFileWrittenBytes(0);
    }

    /**
     * @return void
     * @throws DiskNotWritableException
     * @throws \WPStaging\Framework\Filesystem\FilesystemExceptions
     */
    private function fileBatchWrite()
    {
        $destinationFilePath = $this->extractingFile->getExportPath();

        // Ignore the binary header when importing
        if (strpos($destinationFilePath, 'wpstgBackupHeader.txt') !== false) {
            throw new \OutOfRangeException();
        }

        wp_mkdir_p(dirname($destinationFilePath));

        $destinationFileResource = @fopen($destinationFilePath, FileObject::MODE_APPEND);

        if (!$destinationFileResource) {
            $this->diskWriteCheck->testDiskIsWriteable();
            throw new \Exception("Can not extract file $destinationFilePath");
        }

        while (!$this->extractingFile->isFinished() && !$this->isThreshold()) {
            $writtenBytes = fwrite($destinationFileResource, $this->wpstgFile->fread($this->extractingFile->findReadTo()), $this->getScriptMemoryLimit());

            if ($writtenBytes === false || $writtenBytes <= 0) {
                throw DiskNotWritableException::diskNotWritable();
            }

            $this->extractingFile->addWrittenBytes($writtenBytes);
        }
    }
}
