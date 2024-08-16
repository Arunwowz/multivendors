<?php

// TODO PHP7.x; declare(strict_type=1);
// TODO PHP7.x; type hints & return types
// TODO PHP7.1; constant visibility

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Pro\Backup\BackupScheduler;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Task\ExportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;
use WPStaging\Framework\Utils\Cache\Cache;

class ScheduleBackupTask extends ExportTask
{
    private $backupScheduler;

    public function __construct(BackupScheduler $backupScheduler, LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);
        $this->backupScheduler = $backupScheduler;
    }

    public static function getTaskName()
    {
        return 'backup_export_scheduler';
    }

    public static function getTaskTitle()
    {
        return 'Creating Backup Plan';
    }

    public function execute()
    {
        $scheduleId = wp_generate_password(12, false);

        $this->jobDataDto->setScheduleId($scheduleId);

        $this->backupScheduler->scheduleBackup($this->jobDataDto, $scheduleId);

        $this->logger->info(sprintf('Created scheduled backup plan.'));

        return $this->generateResponse(true);
    }
}
