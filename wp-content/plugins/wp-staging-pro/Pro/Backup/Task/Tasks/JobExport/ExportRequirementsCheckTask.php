<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use RuntimeException;
use WPStaging\Framework\Adapter\Directory;
use WPStaging\Framework\Analytics\Actions\AnalyticsBackupCreate;
use WPStaging\Framework\Filesystem\DiskWriteCheck;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\BackupScheduler;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Exceptions\DiskNotWritableException;
use WPStaging\Pro\Backup\Service\BackupsFinder;
use WPStaging\Pro\Backup\Service\Compressor;
use WPStaging\Pro\Backup\Task\ExportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

class ExportRequirementsCheckTask extends ExportTask
{
    /** @var Directory */
    protected $directory;

    /** @var DiskWriteCheck */
    protected $diskWriteCheck;

    /** @var AnalyticsBackupCreate */
    protected $analyticsBackupCreate;

    protected $backupScheduler;

    /** @var Compressor */
    private $compressor;

    public function __construct(
        Directory $directory,
        LoggerInterface $logger,
        Cache $cache,
        StepsDto $stepsDto,
        SeekableQueueInterface $taskQueue,
        DiskWriteCheck $diskWriteCheck,
        AnalyticsBackupCreate $analyticsBackupCreate,
        BackupScheduler $backupScheduler,
        Compressor $compressor
    ) {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);
        $this->directory = $directory;
        $this->diskWriteCheck = $diskWriteCheck;
        $this->analyticsBackupCreate = $analyticsBackupCreate;
        $this->backupScheduler = $backupScheduler;
        $this->compressor = $compressor;
    }

    public static function getTaskName()
    {
        return 'backup_site_requirements_check';
    }

    public static function getTaskTitle()
    {
        return 'Requirements Check';
    }

    public function execute()
    {
        if (!$this->stepsDto->getTotal()) {
            $this->stepsDto->setTotal(1);
        }

        try {
            $this->shouldWarnIfRunning32Bits();
            $this->cannotExportWithNoStorage();
            $this->cannotExportEmptyBackup();
            $this->cannotImportIfCantWriteToDisk();
            $this->checkFilesystemPermissions();
        } catch (RuntimeException $e) {
            // todo: Set the requirement check fail reason
            $this->analyticsBackupCreate->enqueueFinishEvent($this->jobDataDto->getId(), $this->jobDataDto);
            $this->logger->critical($e->getMessage());

            return $this->generateResponse(false);
        }

        $this->logger->info(__('Backup requirements passed...', 'wp-staging'));

        $this->backupScheduler->maybeDeleteOldBackups($this->jobDataDto);

        $this->maybeCreateMainIndexFile();

        return $this->generateResponse();
    }

    protected function shouldWarnIfRunning32Bits()
    {
        if (PHP_INT_SIZE === 4) {
            $this->logger->warning(__('You are running a 32-bit version of PHP. 32-bits PHP can\'t handle backups larger than 2GB. You might face a critical error. Consider upgrading to 64-bit.', 'wp-staging'));
        }
    }

    protected function cannotExportWithNoStorage()
    {
        if (empty($this->jobDataDto->getStorages())) {
            throw new RuntimeException(__('You must select at least one storage.', 'wp-staging'));
        }
    }

    protected function cannotExportEmptyBackup()
    {
        if (
            !$this->jobDataDto->getIsExportingDatabase()
            && !$this->jobDataDto->getIsExportingPlugins()
            && !$this->jobDataDto->getIsExportingUploads()
            && !$this->jobDataDto->getIsExportingMuPlugins()
            && !$this->jobDataDto->getIsExportingThemes()
            && !$this->jobDataDto->getIsExportingOtherWpContentFiles()
        ) {
            throw new RuntimeException(__('You must select at least one item to export.', 'wp-staging'));
        }
    }

    protected function cannotImportIfCantWriteToDisk()
    {
        try {
            $this->diskWriteCheck->testDiskIsWriteable();
        } catch (DiskNotWritableException $e) {
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * @throws RuntimeException When PHP does not have enough permission to a required directory.
     */
    protected function checkFilesystemPermissions()
    {
        clearstatcache();

        if (!is_writable($this->directory->getPluginUploadsDirectory())) {
            throw new RuntimeException(__(sprintf('PHP does not have enough permission to write to the WP STAGING data directory: %s', $this->directory->getPluginUploadsDirectory()), 'wp-staging'));
        }

        if ($this->jobDataDto->getIsExportingPlugins()) {
            if (!is_readable($this->directory->getPluginsDirectory())) {
                throw new RuntimeException(__(sprintf('PHP does not have enough permission to read the plugins directory: %s', $this->directory->getPluginsDirectory()), 'wp-staging'));
            }
        }

        if ($this->jobDataDto->getIsExportingThemes()) {
            foreach ($this->directory->getAllThemesDirectories() as $themesDirectory) {
                if (!is_readable($themesDirectory)) {
                    throw new RuntimeException(__(sprintf('PHP does not have enough permission to read a themes directory: %s', $themesDirectory), 'wp-staging'));
                }
            }
        }

        if ($this->jobDataDto->getIsExportingMuPlugins()) {
            if (!is_readable($this->directory->getMuPluginsDirectory()) && !wp_mkdir_p($this->directory->getMuPluginsDirectory())) {
                throw new RuntimeException(__(sprintf('PHP does not have enough permission to read the mu-plugins directory: %s', $this->directory->getMuPluginsDirectory()), 'wp-staging'));
            }
        }

        if ($this->jobDataDto->getIsExportingUploads()) {
            if (!is_readable($this->directory->getUploadsDirectory())) {
                throw new RuntimeException(__(sprintf('PHP does not have enough permission to read the uploads directory: %s', $this->directory->getUploadsDirectory()), 'wp-staging'));
            }
        }

        if ($this->jobDataDto->getIsExportingOtherWpContentFiles()) {
            if (!is_readable($this->directory->getWpContentDirectory())) {
                throw new RuntimeException(__(sprintf('PHP does not have enough permission to read the wp-content directory: %s', $this->directory->getWpContentDirectory()), 'wp-staging'));
            }
        }
    }

    protected function maybeCreateMainIndexFile()
    {
        // Early Bail: No need to create a index file it is only a schedule
        if ($this->jobDataDto->getRepeatBackupOnSchedule() && !$this->jobDataDto->getIsCreateScheduleBackupNow()) {
            return;
        }

        // Early Bail: if not split backup
        if (!$this->jobDataDto->getIsMultipartBackup()) {
            return;
        }

        $this->compressor->setCategory('', $create = true);
    }
}
