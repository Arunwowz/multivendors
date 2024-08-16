<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use Exception;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Queue\FinishedQueueException;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\BackupScheduler;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Exceptions\DiskNotWritableException;
use WPStaging\Pro\Backup\Exceptions\StorageException;
use WPStaging\Pro\Backup\Storage\RemoteUploaderInterface;
use WPStaging\Pro\Backup\Task\ExportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

use function WPStaging\functions\debug_log;

abstract class AbstractStorageTask extends ExportTask
{
    const MAX_RETRY = 3;

    protected $retried = 0;

    /** @var RemoteUploaderInterface */
    protected $remoteUploader;

    public function __construct(LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue, RemoteUploaderInterface $remoteUploader)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);

        $this->remoteUploader = $remoteUploader;
    }

    abstract public function getStorageProvider();

    public function execute()
    {
        if ($this->remoteUploader->getError() !== false) {
            $this->logger->warning($this->remoteUploader->getError());
            return $this->generateResponse(false);
        }

        $chunkSize = apply_filters('wpstg.remoteStorages.chunkSize', 5 * MB_IN_BYTES);

        $this->remoteUploader->setupUpload($this->logger, $this->jobDataDto, $chunkSize);

        $this->prepareBackupUpload();

        if ($this->stepsDto->isFinished()) {
            return $this->finishUpload();
        }

        foreach ($this->jobDataDto->getFilesToUpload() as $fileToUpload => $filePath) {
            if (array_key_exists($fileToUpload, $this->jobDataDto->getUploadedFiles())) {
                continue;
            }

            return $this->upload($filePath, $fileToUpload);
        }

        $this->stepsDto->finish();
        return $this->finishUpload();
    }

    protected function upload($exportFilePath, $fileName)
    {
        $canUpload = $this->remoteUploader->setBackupFilePath($exportFilePath, $fileName);
        if (!$canUpload) {
            $this->logger->warning('Error: ' . $this->remoteUploader->getError());
            $this->remoteUploader->stopUpload();
            return $this->generateResponse(false);
        }

        $uploaded = 0;
        $fileSize = filesize($exportFilePath);
        $fileSizeFormatted = size_format($fileSize, 2);
        $this->retried = 0;

        // Delay in requests in milliseconds
        $delay = apply_filters('wpstg.remoteStorages.delayBetweenRequests', 0);
        $delay = filter_var($delay, FILTER_VALIDATE_INT);
        // make sure delay cannot be less than 0
        $delay = max(0, $delay);
        // make sure delay cannot be more than 1 second
        $delay = min(1000, $delay);

        while (!$this->isThreshold()) {
            try {
                $chunkSizeUploaded = $this->remoteUploader->chunkUpload();
                $this->stepsDto->setCurrent($this->stepsDto->getCurrent() + $chunkSizeUploaded);
                $uploaded = $this->jobDataDto->getRemoteStorageMeta()[$fileName]['Offset'];
            } catch (FinishedQueueException $exception) {
                $this->logger->info($this->getProviderName() . ': Uploaded ' . $fileSizeFormatted . '/' . $fileSizeFormatted . ' of backup file: ' . $fileName);
                $this->jobDataDto->setUploadedFile($fileName, $fileSize);
                return $this->generateResponse(false);
            } catch (StorageException $exception) {
                $this->logger->error($exception->getMessage());
                $this->remoteUploader->stopUpload();
                return $this->generateResponse(false);
            } catch (DiskNotWritableException $exception) {
                // Probably disk full. No-op, as this is handled elsewhere.
            } catch (Exception $exception) {
                // Last chunk maybe. No-op
                debug_log('Upload Error: ' . $exception->getMessage());
            }

            if ($uploaded === 0) {
                $this->retried++;
            }

            if ($this->retried > self::MAX_RETRY) {
                $this->sendReport();
                return $this->cancelUpload();
            }

            if ($delay > 0) {
                // convert milliseconds to microseconds for usleep function
                usleep($delay * 1000);
            }
        }

        $uploaded = size_format($uploaded, 2);
        $this->logger->info($this->getProviderName() . ': Uploaded ' . $uploaded . '/' . $fileSizeFormatted . ' of backup file: ' . $fileName);
        $this->remoteUploader->stopUpload();
        return $this->generateResponse(false);
    }

    protected function getProviderName()
    {
        return $this->remoteUploader->getProviderName();
    }

    protected function prepareBackupUpload()
    {
        if ($this->stepsDto->getTotal() > 0) {
            return true;
        }

        $this->jobDataDto->setRemoteStorageMeta([]);

        $deleted = $this->remoteUploader->deleteOldestBackups();
        if (!$deleted) {
            $this->logger->info($this->remoteUploader->getError());

            return false;
        }

        $this->logger->info(sprintf(esc_html__('%s - Deleted oldest backups', 'wp-staging'), $this->getProviderName()));
        $this->stepsDto->setTotal($this->jobDataDto->getTotalBackupSize());
        $this->stepsDto->setCurrent(0);
        $this->logger->info('Initiate backup upload to ' . $this->getProviderName() . '.');
        return true;
    }

    protected function finishUpload()
    {
        $this->remoteUploader->stopUpload();
        $this->jobDataDto->setEndTime(time());
        $this->logger->info('Backup uploads finished for ' . $this->getProviderName() . '.');
        $this->logger->info('Verifying Upload for ' . $this->getProviderName() . '.');
        if (!$this->remoteUploader->verifyUploads($this->jobDataDto->getUploadedFiles())) {
            $this->logger->warning("Couldn't verify upload to " . $this->getProviderName() . '.');
            return $this->generateResponse(false);
        }

        $this->logger->info('Upload Verified.');
        return $this->generateResponse(false);
    }

    private function cancelUpload()
    {
        $this->remoteUploader->stopUpload();
        $this->jobDataDto->setEndTime(time());
        $this->logger->warning($this->getStorageProvider() . ' - Upload Cancelled: Unable to upload backup');
        return $this->generateResponse(false);
    }

    private function sendReport()
    {
        if (!$this->jobDataDto->getIsAutomatedBackup()) {
            return;
        }

        /** @var BackupScheduler */
        $backupScheduler = WPStaging::make(BackupScheduler::class);
        $backupScheduler->sendErrorReport("Unable to upload to remote storage provider: " . $this->getStorageProvider());
    }
}
