<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport\RemoteStorageTasks;

use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Storage\Storages\Wasabi\Uploader;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\AbstractStorageTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

class WasabiStorageTask extends AbstractStorageTask
{
    public function __construct(LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue, Uploader $remoteUploader)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue, $remoteUploader);
    }

    public function getStorageProvider()
    {
        return 'Wasabi S3';
    }

    public static function getTaskName()
    {
        return 'backup_export_wasabi_upload';
    }

    public static function getTaskTitle()
    {
        return 'Uploading Backup to Wasabi';
    }
}
