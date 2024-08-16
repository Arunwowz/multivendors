<?php

namespace WPStaging\Pro\Backup\Job\Jobs;

use RuntimeException;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Analytics\Actions\AnalyticsBackupRestore;
use WPStaging\Pro\Backup\Dto\Job\JobImportDataDto;
use WPStaging\Pro\Backup\Job\AbstractJob;
use WPStaging\Pro\Backup\Entity\BackupMetadata;
use WPStaging\Pro\Backup\Task\Tasks\CleanupTmpTablesTask;
use WPStaging\Pro\Backup\Task\Tasks\CleanupTmpFilesTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\CleanExistingMediaTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\ExtractFilesTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\ImportLanguageFilesTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\ImportOtherFilesInWpContentTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\RenameDatabaseTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\RestoreRequirementsCheckTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\ImportDatabaseTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\ImportMuPluginsTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\ImportPluginsTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\ImportThemesTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\UpdateBackupsScheduleTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\UpdateDomainPathTask;
use WPStaging\Pro\Backup\Task\Tasks\JobImport\UpdateSubsiteSiteHomeUrlTask;

class JobImport extends AbstractJob
{
    const TMP_DIRECTORY = 'tmp/import/';

    /** @var JobImportDataDto $jobDataDto */
    protected $jobDataDto;

    /** @var array The array of tasks to execute for this job. Populated at init(). */
    private $tasks = [];

    public static function getJobName()
    {
        return 'backup_import';
    }

    protected function getJobTasks()
    {
        return $this->tasks;
    }

    public function onWpShutdown()
    {
        if ($this->jobDataDto->isFinished()) {
            WPStaging::make(AnalyticsBackupRestore::class)->enqueueFinishEvent($this->jobDataDto->getId(), $this->jobDataDto);
        }

        parent::onWpShutdown();
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
        if ($this->jobDataDto->getBackupMetadata()) {
            return;
        }

        try {
            $backupMetadata = (new BackupMetadata())->hydrateByFilePath($this->jobDataDto->getFile());
        } catch (\Exception $e) {
            throw $e;
        }

        if (!$this->isValidMetadata($backupMetadata)) {
            throw new RuntimeException('Failed to get backup metadata.');
        }

        $this->jobDataDto->setBackupMetadata($backupMetadata);
        $this->jobDataDto->setTmpDirectory($this->getJobTmpDirectory());
        $this->jobDataDto->setIsSameSiteBackupRestore($this->isSameSiteBackupRestore($backupMetadata));

        $this->tasks[] = CleanupTmpFilesTask::class;
        $this->tasks[] = CleanupTmpTablesTask::class;
        $this->tasks[] = RestoreRequirementsCheckTask::class;

        if ($backupMetadata->getIsExportingUploads()) {
            $this->tasks[] = CleanExistingMediaTask::class;
        }

        $this->addExtractFilesTasks();

        if ($backupMetadata->getIsExportingThemes()) {
            $this->tasks[] = ImportThemesTask::class;
        }

        if ($backupMetadata->getIsExportingPlugins()) {
            $this->tasks[] = ImportPluginsTask::class;
        }

        if (
            $backupMetadata->getIsExportingThemes()
            || $backupMetadata->getIsExportingPlugins()
            || $backupMetadata->getIsExportingMuPlugins()
            || $backupMetadata->getIsExportingOtherWpContentFiles()
        ) {
            $this->tasks[] = ImportLanguageFilesTask::class;
        }

        if ($backupMetadata->getIsExportingOtherWpContentFiles()) {
            $this->tasks[] = ImportOtherFilesInWpContentTask::class;
        }

        if ($backupMetadata->getIsExportingDatabase()) {
            $this->addDatabaseTasks();
        }

        if ($backupMetadata->getIsExportingMuPlugins()) {
            $this->tasks[] = ImportMuPluginsTask::class;
        }

        $this->tasks[] = CleanupTmpFilesTask::class;
    }

    /**
     * @param $backupMetadata
     * @return bool
     */
    private function isSameSiteBackupRestore($backupMetadata)
    {
        if (is_multisite()) {
            $isSameSite = site_url() === $backupMetadata->getSiteUrl() &&
                ABSPATH === $backupMetadata->getAbsPath() &&
                is_subdomain_install() === $backupMetadata->getSubdomainInstall();
        } else {
            $isSameSite = site_url() === $backupMetadata->getSiteUrl() &&
                ABSPATH === $backupMetadata->getAbsPath();
        }

        return $isSameSite;
    }

    /**
     * @return string
     */
    private function getJobTmpDirectory()
    {
        $dir = $this->directory->getTmpDirectory() . $this->jobDataDto->getId();
        $this->filesystem->mkdir($dir);

        return trailingslashit($dir);
    }

    private function addDatabaseTasks()
    {
        $metadata = $this->jobDataDto->getBackupMetadata();
        if ($metadata->getIsMultipartBackup()) {
            foreach ($metadata->getMultipartMetadata()->getDatabaseParts() as $part) {
                $this->tasks[] = ImportDatabaseTask::class;
            }
        } else {
            $this->tasks[] = ImportDatabaseTask::class;
        }

        if (is_multisite() && is_main_site()) {
            $this->tasks[] = UpdateDomainPathTask::class;
            $this->tasks[] = UpdateSubsiteSiteHomeUrlTask::class;
        }

        $this->tasks[] = UpdateBackupsScheduleTask::class;
        $this->tasks[] = RenameDatabaseTask::class;
        $this->tasks[] = CleanupTmpTablesTask::class;
    }

    private function addExtractFilesTasks()
    {
        $metadata = $this->jobDataDto->getBackupMetadata();
        if (!$metadata->getIsMultipartBackup()) {
            $this->tasks[] = ExtractFilesTask::class;
            return;
        }

        foreach ($metadata->getMultipartMetadata()->getFileParts() as $part) {
            $this->tasks[] = ExtractFilesTask::class;
        }
    }

    /**
     * @param BackupMetadata $metadata
     *
     * @return bool
     */
    private function isValidMetadata($metadata)
    {
        $extension = pathinfo($this->jobDataDto->getFile(), PATHINFO_EXTENSION);
        if ($extension !== 'sql') {
            return !empty($metadata->getHeaderStart());
        }

        return !empty($metadata->getMaxTableLength()) && !empty($metadata->getMultipartMetadata()->getDatabaseParts());
    }
}
