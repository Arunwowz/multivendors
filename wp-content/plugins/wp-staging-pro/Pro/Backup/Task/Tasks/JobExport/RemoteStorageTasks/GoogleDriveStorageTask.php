<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport\RemoteStorageTasks;

use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Storage\Storages\GoogleDrive\Uploader as DriveUploader;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\AbstractStorageTask as AbstractStorageTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

class GoogleDriveStorageTask extends AbstractStorageTask
{
    public function __construct(LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue, DriveUploader $remoteUploader)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue, $remoteUploader);
    }

    public function getStorageProvider()
    {
        return 'Google Drive';
    }

    public static function getTaskName()
    {
        return 'backup_export_google_drive_upload';
    }

    public static function getTaskTitle()
    {
        return 'Uploading Backup to Google Drive';
    }
}
