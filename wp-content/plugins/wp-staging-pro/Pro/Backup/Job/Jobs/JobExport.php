<?php

namespace WPStaging\Pro\Backup\Job\Jobs;

use WPStaging\Pro\Backup\Dto\Job\JobExportDataDto;
use WPStaging\Pro\Backup\Job\AbstractJob;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\DatabaseExportTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportMuPluginsTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportOtherFilesTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportPluginsTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportRequirementsCheckTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportThemesTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportUploadsTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\FilesystemScannerTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\FinalizeExportTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\FinishBackupTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ValidateBackupTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\IncludeDatabaseTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\RemoteStorageTasks\AmazonS3StorageTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\RemoteStorageTasks\DigitalOceanSpacesStorageTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\RemoteStorageTasks\GenericS3StorageTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\RemoteStorageTasks\GoogleDriveStorageTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\RemoteStorageTasks\SftpStorageTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\RemoteStorageTasks\WasabiStorageTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ScheduleBackupTask;

class JobExport extends AbstractJob
{
    /** @var JobExportDataDto $jobDataDto */
    protected $jobDataDto;

    /** @var array The array of tasks to execute for this job. Populated at init(). */
    private $tasks = [];

    public static function getJobName()
    {
        return 'backup_export';
    }

    protected function getJobTasks()
    {
        return $this->tasks;
    }

    protected function execute()
    {
        $this->startBenchmark();

        try {
            $response = $this->getResponse($this->currentTask->execute());
        } catch (\Exception $e) {
            $this->currentTask->getLogger()->critical($e->getMessage());
            $response = $this->getResponse($this->currentTask->generateResponse(false));
        }

        $this->finishBenchmark(get_class($this->currentTask));

        return $response;
    }

    protected function init()
    {
        $this->tasks[] = ExportRequirementsCheckTask::class;
        if ($this->jobDataDto->getRepeatBackupOnSchedule() && !$this->jobDataDto->getIsCreateScheduleBackupNow()) {
            $this->tasks[] = ScheduleBackupTask::class;
            return;
        }

        $this->tasks[] = FilesystemScannerTask::class;
        if ($this->jobDataDto->getIsExportingOtherWpContentFiles()) {
            $this->tasks[] = ExportOtherFilesTask::class;
        }

        if ($this->jobDataDto->getIsExportingPlugins()) {
            $this->tasks[] = ExportPluginsTask::class;
        }

        if ($this->jobDataDto->getIsExportingMuPlugins()) {
            $this->tasks[] = ExportMuPluginsTask::class;
        }

        if ($this->jobDataDto->getIsExportingThemes()) {
            $this->tasks[] = ExportThemesTask::class;
        }

        if ($this->jobDataDto->getIsExportingUploads()) {
            $this->tasks[] = ExportUploadsTask::class;
        }

        if ($this->jobDataDto->getIsExportingDatabase()) {
            $this->tasks[] = DatabaseExportTask::class;
        }

        if ($this->jobDataDto->getIsExportingDatabase() && !$this->jobDataDto->getIsMultipartBackup()) {
            $this->tasks[] = IncludeDatabaseTask::class;
        }

        $this->tasks[] = FinalizeExportTask::class;
        if ($this->jobDataDto->getRepeatBackupOnSchedule()) {
            $this->tasks[] = ScheduleBackupTask::class;
        }

        $this->tasks[] = ValidateBackupTask::class;

        $this->addStorageTasks();

        $this->tasks[] = FinishBackupTask::class;
    }

    protected function addStorageTasks()
    {
        if ($this->jobDataDto->isUploadToGoogleDrive()) {
            $this->tasks[] = GoogleDriveStorageTask::class;
        }

        if ($this->jobDataDto->isUploadToAmazonS3()) {
            $this->tasks[] = AmazonS3StorageTask::class;
        }

        if ($this->jobDataDto->isUploadToSftp()) {
            $this->tasks[] = SftpStorageTask::class;
        }

        if ($this->jobDataDto->isUploadToDigitalOceanSpaces()) {
            $this->tasks[] = DigitalOceanSpacesStorageTask::class;
        }

        if ($this->jobDataDto->isUploadToWasabi()) {
            $this->tasks[] = WasabiStorageTask::class;
        }

        if ($this->jobDataDto->isUploadToGenericS3()) {
            $this->tasks[] = GenericS3StorageTask::class;
        }

        $this->tasks[] = FinishBackupTask::class;
    }
}
