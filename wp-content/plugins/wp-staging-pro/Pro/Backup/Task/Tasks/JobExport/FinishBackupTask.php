<?php

// TODO PHP7.x; declare(strict_type=1);
// TODO PHP7.x; type hints & return types
// TODO PHP7.1; constant visibility

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use WPStaging\Framework\Analytics\Actions\AnalyticsBackupCreate;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Dto\TaskResponseDto;
use WPStaging\Pro\Backup\Dto\Task\Export\Response\FinalizeExportResponseDto;
use WPStaging\Pro\Backup\Entity\ListableBackup;
use WPStaging\Pro\Backup\Task\ExportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

class FinishBackupTask extends ExportTask
{
    /** @var AnalyticsBackupCreate */
    protected $analyticsBackupCreate;

    const OPTION_LAST_BACKUP = 'wpstg_last_backup_info';

    public function __construct(LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue, AnalyticsBackupCreate $analyticsBackupCreate)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);

        $this->analyticsBackupCreate = $analyticsBackupCreate;
    }

    public static function getTaskName()
    {
        return 'backup_export_finish';
    }

    public static function getTaskTitle()
    {
        return 'Finalizing Backup Export';
    }

    public function execute()
    {
        $exportFilePath = $this->jobDataDto->getBackupFilePath();

        $this->analyticsBackupCreate->enqueueFinishEvent($this->jobDataDto->getId(), $this->jobDataDto);

        $this->stepsDto->finish();

        update_option(static::OPTION_LAST_BACKUP, [
            'endTime' => time(), // Unix timestamp is timezone independent
            'duration' => $this->jobDataDto->getDuration(),
            'JobExportDataDto' => $this->jobDataDto,
        ], false);

        $this->jobDataDto->setEndTime(time());

        return $this->overrideGenerateResponse($this->makeListableBackup($exportFilePath));
    }

    /**
     * @param null|ListableBackup $backup
     *
     * @return FinalizeExportResponseDto|TaskResponseDto
     */
    private function overrideGenerateResponse(ListableBackup $backup = null)
    {
        add_filter('wpstg.task.response', function ($response) use ($backup) {

            $md5 = $backup ? $backup->md5BaseName : null;
            if ($this->jobDataDto->getIsMultipartBackup()) {
                $md5 = $this->getPartsMd5();
            }

            if ($response instanceof FinalizeExportResponseDto) {
                $response->setBackupMd5($md5);
                $response->setBackupSize($backup ? size_format($backup->size) : null);
                $response->setIsLocalBackup($this->jobDataDto->isLocalBackup());
                $response->setIsMultipartBackup($this->jobDataDto->getIsMultipartBackup());
            }

            return $response;
        });

        return $this->generateResponse();
    }

    protected function getResponseDto()
    {
        return new FinalizeExportResponseDto();
    }

    /**
     * This is used to display the "Download Modal" after the backup completes.
     *
     * @see string src/Backend/public/js/wpstg-admin.js, search for "wpstg--backups--export"
     *
     * @param string $exportFilePath
     *
     * @return ListableBackup
     */
    protected function makeListableBackup($exportFilePath)
    {
        clearstatcache();
        $backup = new ListableBackup();
        $backup->md5BaseName = md5(basename($exportFilePath));
        $backup->size = filesize($exportFilePath);

        return $backup;
    }

    protected function getPartsMd5()
    {
        $md5 = [];
        foreach ($this->jobDataDto->getMoveBackupPartsActions() as $destinationFile => $source) {
            $md5[] = md5($destinationFile);
        }

        return $md5;
    }
}
