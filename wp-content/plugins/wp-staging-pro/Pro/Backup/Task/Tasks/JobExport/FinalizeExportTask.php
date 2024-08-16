<?php

// TODO PHP7.x; declare(strict_type=1);
// TODO PHP7.x; type hints & return types
// TODO PHP7.1; constant visibility

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use Exception;
use RuntimeException;
use SplFileObject;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Adapter\Database;
use WPStaging\Framework\Adapter\Directory;
use WPStaging\Framework\Analytics\Actions\AnalyticsBackupCreate;
use WPStaging\Framework\Filesystem\FileObject;
use WPStaging\Framework\Filesystem\PathIdentifier;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Framework\Utils\Cache\BufferedCache;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Dto\Task\Export\Response\FinalizeExportResponseDto;
use WPStaging\Pro\Backup\Entity\BackupMetadata;
use WPStaging\Pro\Backup\Entity\MultipartMetadata;
use WPStaging\Pro\Backup\Service\BackupMetadataEditor;
use WPStaging\Pro\Backup\Service\BackupsFinder;
use WPStaging\Pro\Backup\Task\ExportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;
use WPStaging\Pro\Backup\Service\Compressor;
use WPStaging\Pro\Backup\WithBackupIdentifier;

class FinalizeExportTask extends ExportTask
{
    use WithBackupIdentifier;

    /** @var Compressor */
    private $compressor;
    private $wpdb;

    /** @var PathIdentifier */
    protected $pathIdentifier;

    /** @var BackupMetadataEditor */
    protected $backupMetadataEditor;

    /** @var AnalyticsBackupCreate */
    protected $analyticsBackupCreate;

    /** @var BufferedCache */
    protected $sqlCache;

    /** @var array */
    protected $databaseParts = [];

    public function __construct(Compressor $compressor, BufferedCache $sqlCache, LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue, PathIdentifier $pathIdentifier, BackupMetadataEditor $backupMetadataEditor, AnalyticsBackupCreate $analyticsBackupCreate)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);

        global $wpdb;
        $this->compressor = $compressor;
        $this->sqlCache = $sqlCache;
        $this->wpdb = $wpdb;
        $this->pathIdentifier = $pathIdentifier;
        $this->backupMetadataEditor = $backupMetadataEditor;
        $this->analyticsBackupCreate = $analyticsBackupCreate;
    }

    public static function getTaskName()
    {
        return 'backup_export_combine';
    }

    public static function getTaskTitle()
    {
        return 'Preparing Backup File';
    }

    public function execute()
    {
        $compressorDto = $this->compressor->getDto();

        $backupMetadata = $compressorDto->getBackupMetadata();
        $backupMetadata->setId($this->jobDataDto->getId());
        $backupMetadata->setTotalDirectories($this->jobDataDto->getTotalDirectories());
        $backupMetadata->setTotalFiles($this->jobDataDto->getTotalFiles());
        $backupMetadata->setName($this->jobDataDto->getName());
        $backupMetadata->setIsAutomatedBackup($this->jobDataDto->getIsAutomatedBackup());

        global $wpdb;
        $backupMetadata->setPrefix($wpdb->base_prefix);

        // What the backup exports
        $backupMetadata->setIsExportingPlugins($this->jobDataDto->getIsExportingPlugins());
        $backupMetadata->setIsExportingMuPlugins($this->jobDataDto->getIsExportingMuPlugins());
        $backupMetadata->setIsExportingThemes($this->jobDataDto->getIsExportingThemes());
        $backupMetadata->setIsExportingUploads($this->jobDataDto->getIsExportingUploads());
        $backupMetadata->setIsExportingOtherWpContentFiles($this->jobDataDto->getIsExportingOtherWpContentFiles());
        $backupMetadata->setIsExportingDatabase($this->jobDataDto->getIsExportingDatabase());
        $backupMetadata->setScheduleId($this->jobDataDto->getScheduleId());
        $backupMetadata->setMultipartMetadata(null);

        $this->addSystemInfoToBackupMetadata($backupMetadata);

        if ($this->jobDataDto->getIsExportingDatabase()) {
            $backupMetadata->setDatabaseFile($this->pathIdentifier->transformPathToIdentifiable($this->jobDataDto->getDatabaseFile()));
            $backupMetadata->setDatabaseFileSize($this->jobDataDto->getDatabaseFileSize());

            $maxTableLength = 0;
            foreach ($this->jobDataDto->getTablesToExport() as $table) {
                // Get the biggest table name, without the prefix.
                $maxTableLength = max($maxTableLength, strlen(substr($table, strlen($this->wpdb->base_prefix))));
            }

            $backupMetadata->setMaxTableLength($maxTableLength);

            $backupMetadata->setNonWpTables($this->jobDataDto->getNonWpTables());
        }

        $backupMetadata->setPlugins(array_keys(get_plugins()));

        $backupMetadata->setMuPlugins(array_keys(get_mu_plugins()));

        $backupMetadata->setThemes(array_keys(search_theme_directories()));

        if (is_multisite() && is_main_site()) {
            $backupMetadata->setSites($this->jobDataDto->getSitesToExport());
        }

        $isLocalBackup = $this->jobDataDto->isLocalBackup();
        $isUploadBackup = count($this->jobDataDto->getStorages()) > 0;
        if ($this->jobDataDto->getIsMultipartBackup()) {
            $this->addSplitMetadata($backupMetadata, $isUploadBackup);
        } elseif ($isUploadBackup) {
            $backupFinalPath = $this->compressor->getFinalPath('', $isLocalBackup);
            $backupName = basename($backupFinalPath);
            $filesToUpload = [];
            $filesToUpload[$backupName] = $backupFinalPath;
            $this->jobDataDto->setFilesToUpload($filesToUpload);
        }

        try {
            $this->compressor->setIsLocalBackup($isLocalBackup);

            $this->addBackupMetadata($backupMetadata);

            $this->stepsDto->finish();

            if ($isLocalBackup) {
                $this->logger->info(esc_html__('Successfully created backup file', 'wp-staging'));
            } else {
                $this->logger->info(esc_html__('Successfully generated backup metadata', 'wp-staging'));
            }

            return $this->generateResponse(false);
        } catch (Exception $e) {
            if ($isLocalBackup) {
                $this->logger->critical('Failed to create backup file: ' . $e->getMessage());
            } else {
                $this->logger->critical('Failed to generate backup metadata: ' . $e->getMessage());
            }
        }


        $steps = $this->stepsDto;
        $steps->setCurrent($compressorDto->getWrittenBytesTotal());
        $steps->setTotal($compressorDto->getFileSize());

        $this->logger->info(sprintf('Written %d bytes to compressed export', $compressorDto->getWrittenBytesTotal()));

        return $this->generateResponse(false);
    }

    /**
     * @see \wp_version_check
     * @see https://codex.wordpress.org/Converting_Database_Character_Sets
     */
    protected function addSystemInfoToBackupMetadata(BackupMetadata &$backupMetadata)
    {
        /**
         * @var string $wp_version
         * @var int    $wp_db_version
         */
        include ABSPATH . WPINC . '/version.php';

        /** @var Database */
        $database = WPStaging::make(Database::class);

        $serverType = $database->getServerType();
        $mysqlVersion = $database->getSqlVersion($compact = true);

        $backupMetadata->setPhpVersion(phpversion());
        $backupMetadata->setWpVersion($wp_version);
        /** @phpstan-ignore-line */
        $backupMetadata->setWpDbVersion($wp_db_version);
        /** @phpstan-ignore-line */
        $backupMetadata->setDbCollate($this->wpdb->collate);
        $backupMetadata->setDbCharset($this->wpdb->charset);
        $backupMetadata->setSqlServerVersion($serverType . ' ' . $mysqlVersion);
    }

    protected function getResponseDto()
    {
        return new FinalizeExportResponseDto();
    }

    /**
     * @param BackupMetadata $backupMetadata
     * @param bool $isUploadBackup
     * @throws RuntimeException
     */
    protected function addSplitMetadata($backupMetadata, $isUploadBackup)
    {
        $backupsDirectory = '';
        if ($this->jobDataDto->isLocalBackup()) {
            $backupsDirectory = WPStaging::make(BackupsFinder::class)->getBackupsDirectory();
        } else {
            $backupsDirectory = WPStaging::make(Directory::class)->getCacheDirectory();
        }

        $filesToUpload = [];

        $splitMetadata = new MultipartMetadata();

        foreach ($this->jobDataDto->getMoveBackupPartsActions() as $destinationFile => $source) {
            $destination = $backupsDirectory . $destinationFile;

            if ($isUploadBackup) {
                $filesToUpload[$destinationFile] = $destination;
            }

            $dbExtension = DatabaseExportTask::FILE_FORMAT;
            $dbIdentifier = DatabaseExportTask::PART_IDENTIFIER;
            if (preg_match("#.{$dbIdentifier}(.[0-9]+)?.{$dbExtension}$#", $destinationFile)) {
                $this->databaseParts[$source] = $destination;
                $splitMetadata->pushBackupPart('database', $destinationFile);
                continue;
            }

            if ($this->checkPartByIdentifier(ExportMuPluginsTask::IDENTIFIER, $destinationFile)) {
                $splitMetadata->pushBackupPart('muplugins', $destinationFile);
                continue;
            }

            if ($this->checkPartByIdentifier(ExportPluginsTask::IDENTIFIER, $destinationFile)) {
                $splitMetadata->pushBackupPart('plugins', $destinationFile);
                continue;
            }

            if ($this->checkPartByIdentifier(ExportThemesTask::IDENTIFIER, $destinationFile)) {
                $splitMetadata->pushBackupPart('themes', $destinationFile);
                continue;
            }

            if ($this->checkPartByIdentifier(ExportUploadsTask::IDENTIFIER, $destinationFile)) {
                $splitMetadata->pushBackupPart('uploads', $destinationFile);
                continue;
            }

            if ($this->checkPartByIdentifier(ExportOtherFilesTask::IDENTIFIER, $destinationFile)) {
                $splitMetadata->pushBackupPart('others', $destinationFile);
                continue;
            }
        }

        $this->jobDataDto->setFilesToUpload($filesToUpload);
        $backupMetadata->setMultipartMetadata($splitMetadata);
    }

    /** @param BackupMetadata $backupMetadata */
    protected function addBackupMetadata($backupMetadata)
    {
        if (!$this->jobDataDto->getIsMultipartBackup()) {
            // Write the Backup metadata
            $backupFilePath = $this->compressor->generateBackupMetadata();
            $this->jobDataDto->setBackupFilePath($backupFilePath);

            return;
        }

        foreach ($this->databaseParts as $tmpSqlFilePath => $destinationSqlFilePath) {
            $this->addMetadataToSql($backupMetadata, $tmpSqlFilePath, $destinationSqlFilePath);
        }

        $splitMetadata = $backupMetadata->getMultipartMetadata();

        foreach ($splitMetadata->getPluginsParts() as $index => $part) {
            $this->compressor->setBackupMetadataForBackupPart(ExportPluginsTask::IDENTIFIER, $part, $index);
        }

        foreach ($splitMetadata->getMuPluginsParts() as $index => $part) {
            $this->compressor->setBackupMetadataForBackupPart(ExportMuPluginsTask::IDENTIFIER, $part, $index);
        }

        foreach ($splitMetadata->getThemesParts() as $index => $part) {
            $this->compressor->setBackupMetadataForBackupPart(ExportThemesTask::IDENTIFIER, $part, $index);
        }

        foreach ($splitMetadata->getUploadsParts() as $index => $part) {
            $this->compressor->setBackupMetadataForBackupPart(ExportUploadsTask::IDENTIFIER, $part, $index);
        }

        foreach ($splitMetadata->getOthersParts() as $index => $part) {
            $this->compressor->setBackupMetadataForBackupPart(ExportOtherFilesTask::IDENTIFIER, $part, $index);
        }
    }

    /**
     * @param BackupMetadata $backupMetadata
     * @param string $tmpSqlFilePath
     * @param string $destinationSqlFilePath
     */
    protected function addMetadataToSql($backupMetadata, $tmpSqlFilePath, $destinationSqlFilePath)
    {
        $sqlHandle = fopen($tmpSqlFilePath, 'a');
        fwrite($sqlHandle, PHP_EOL);
        fwrite($sqlHandle, '-- ' . json_encode($backupMetadata) . PHP_EOL);
        fclose($sqlHandle);

        if (!rename($tmpSqlFilePath, $destinationSqlFilePath)) {
            throw new RuntimeException("");
        }
    }
}
