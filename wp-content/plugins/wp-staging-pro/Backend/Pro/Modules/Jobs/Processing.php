<?php

namespace WPStaging\Backend\Pro\Modules\Jobs;

use WPStaging\Framework\Analytics\Actions\AnalyticsStagingPush;
use WPStaging\Framework\Security\AccessToken;
use WPStaging\Core\WPStaging;
use RuntimeException;
use WPStaging\Backend\Modules\Jobs\Job;
use WPStaging\Core\Utils\Logger;
use WPStaging\Framework\Database\SelectedTables;
use WPStaging\Framework\Filesystem\Filesystem;
use WPStaging\Framework\Staging\Sites;
use WPStaging\Framework\Utils\Sanitize;
use WPStaging\Pro\Backup\Ajax\Export\PrepareExport;
use WPStaging\Pro\Backup\Dto\TaskResponseDto;
use WPStaging\Framework\Utils\WpDefaultDirectories;
use WPStaging\Pro\Backup\BackupDeleter;
use WPStaging\Pro\Backup\Job\Jobs\JobExport;

/**
 * Class Processing
 * Collect clone and job data and delegate all further separate job modules
 * @package WPStaging\Backend\Pro\Modules\Jobs
 */
class Processing extends Job
{
    use BackupTrait;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Sanitize
     */
    private $sanitize;

    /**
     * Initialize is called in \Job
     */
    public function initialize()
    {
        $this->sanitize = WPStaging::make(Sanitize::class);
    }

    /**
     * Start the cloning job
     */
    public function start()
    {
        /** @var Filesystem */
        $this->filesystem = WPStaging::make(Filesystem::class);

        // Save default job settings to cache file
        $this->init();

        $methodName = $this->options->currentJob;

        if (!method_exists($this, $methodName)) {
            // If method not exists, start over with default action
            $methodName = 'jobFinish';
            $this->log("Processing: Force method '{$methodName}'");
            $this->cache->delete("clone_options");
            $this->cache->delete("files_to_copy");
            // Save default job settings and create clone_options with default settings
            $this->init();
        }

        // Call the job
        return $this->{$methodName}();
    }

    /**
     * Save processing default settings
     * @return bool
     */
    private function init()
    {
        // Make sure this runs one time only on start of processing
        if (!isset($_POST) || !isset($_POST["clone"]) || !empty($this->options->currentJob)) {
            return false;
        }

        // Delete old job files initially
        $this->cache->delete('clone_options');
        $this->cache->delete('files_to_copy');

        // Basic Options
        $this->options->root = str_replace(["\\", '/'], DIRECTORY_SEPARATOR, ABSPATH);
        $this->options->existingClones = get_option(Sites::STAGING_SITES_OPTION, []);

        $cloneID = $this->sanitize->sanitizeString($_POST['clone']);

        if (array_key_exists($cloneID, $this->options->existingClones)) {
            $this->options->current = $cloneID;
            $this->options->databaseUser = $this->options->existingClones[strtolower($this->options->current)]['databaseUser'];
            $this->options->databasePassword = $this->options->existingClones[strtolower($this->options->current)]['databasePassword'];
            $this->options->databaseDatabase = $this->options->existingClones[strtolower($this->options->current)]['databaseDatabase'];
            $this->options->databaseServer = $this->options->existingClones[strtolower($this->options->current)]['databaseServer'];
            $this->options->databasePrefix = $this->options->existingClones[strtolower($this->options->current)]['databasePrefix'];
            $this->options->url = $this->options->existingClones[strtolower($this->options->current)]['url'];
            $this->options->path = $this->filesystem->normalizePath($this->options->existingClones[strtolower($this->options->current)]['path'], true);
            $this->options->uploadsSymlinked = isset($this->options->existingClones[strtolower($this->options->current)]['uploadsSymlinked']) ? $this->options->existingClones[strtolower($this->options->current)]['uploadsSymlinked'] : false;
            $this->options->networkClone = isset($this->options->existingClones[$this->options->current]['networkClone']) ? (bool)$this->options->existingClones[strtolower($this->options->current)]['networkClone'] : false;
        }

        // Clone
        $this->options->clone = $cloneID;
        $this->options->cloneDirectoryName = preg_replace("#\W+#", '-', strtolower($this->options->clone));
        $this->options->cloneNumber = $this->options->existingClones[strtolower($this->options->clone)]['number'];
        $this->options->prefix = $this->getPrefix();
        $this->options->mainJob = 'pushing';

        $this->options->tablePushSelection = [];
        $this->options->excludedTables = [];
        $this->options->tables = [];
        $this->options->clonedTables = [];

        // Files
        $this->options->totalFiles = 0;
        $this->options->copiedFiles = 0;

        // Directories
        $this->options->includedDirectories = [];
        $this->options->excludedDirectories = [];
        $this->options->extraDirectories = [];
        $this->options->directoriesToCopy = [];
        $this->options->scannedDirectories = [];

        // TODO REF: Job Queue; FIFO
        // Job

        if (is_multisite() && !is_main_site() && !$this->isNetworkClone()) {
            // We don't support backup of multisite subsite as of now
            $this->options->currentJob = 'jobFileScanning';
        } else {
            $this->options->currentJob = 'jobDeleteOldBackups';
        }

        $this->options->currentStep = 0;
        $this->options->totalSteps = 0;


        // Create new Job object
        $this->options->job = new \stdClass();

        // Selected Tables POST
        $includedTables = isset($_POST['includedTables']) ? $this->sanitize->sanitizeString($_POST['includedTables']) : '';
        $excludedTables = isset($_POST['excludedTables']) ? $this->sanitize->sanitizeString($_POST['excludedTables']) : '';
        $selectedTablesWithoutPrefix = isset($_POST['selectedTablesWithoutPrefix']) ? $this->sanitize->sanitizeString($_POST['selectedTablesWithoutPrefix']) : '';
        $selectedTables = new SelectedTables($includedTables, $excludedTables, $selectedTablesWithoutPrefix);
        $selectedTables->setAllTablesExcluded(empty($_POST['allTablesExcluded']) ? false : $this->sanitize->sanitizeBool($_POST['allTablesExcluded']));
        $selectedTables->setDatabaseInfo($this->options->databaseServer, $this->options->databaseUser, $this->options->databasePassword, $this->options->databaseDatabase, empty($this->options->databasePrefix) ? $this->options->prefix : $this->options->databasePrefix);
        $tables = $selectedTables->getSelectedTables($this->options->networkClone);
        $this->options->tables = $tables;
        $this->options->tablePushSelection = $tables;

        // Excluded Directories POST
        if (isset($_POST["excludedDirectories"]) && is_array($_POST["excludedDirectories"])) {
            $this->options->excludedDirectories = $this->sanitize->sanitizeString($_POST["excludedDirectories"]);
        }

        // Included Directories POST
        if (isset($_POST["includedDirectories"]) && is_array($_POST["includedDirectories"])) {
            $this->options->includedDirectories = $this->sanitize->sanitizeString($_POST["includedDirectories"]);
        }

        // Extra Directories POST
        if (isset($_POST["extraDirectories"]) && !empty($_POST["extraDirectories"])) {
            $this->options->extraDirectories = array_map('trim', $this->sanitize->sanitizeString($_POST["extraDirectories"]));
        }

        // Never copy these folders
        $excludedDirectories = [
            $this->options->path . 'wp-content/plugins/wp-staging-pro',
            $this->options->path . 'wp-content/plugins/wp-staging-pro-1',
            $this->options->path . 'wp-content/plugins/wp-staging',
            $this->options->path . 'wp-content/plugins/wp-staging-1',
            $this->options->path . 'wp-content/uploads/wp-staging',
        ];

        // Add upload folder to list of excluded directories for push if symlink option is enabled
        if ($this->options->uploadsSymlinked) {
            $wpUploadsFolder = $this->options->path . (new WpDefaultDirectories())->getRelativeUploadPath();
            $excludedDirectories[] = rtrim($wpUploadsFolder, '/\\');
        }

        // Delete uploads folder before pushing
        $this->options->deleteUploadsFolder = !$this->options->uploadsSymlinked && isset($_POST['deleteUploadsBeforePushing']) && $this->sanitize->sanitizeBool($_POST['deleteUploadsBeforePushing']);
        // backup uploads folder before deleting
        $this->options->backupUploadsFolder = $this->options->deleteUploadsFolder && isset($_POST['backupUploadsBeforePushing']) && $this->sanitize->sanitizeBool($_POST['backupUploadsBeforePushing']);
        // Delete all plugins and themes not used in staging site
        $this->options->deletePluginsAndThemes = isset($_POST['deletePluginsAndThemes']) && $this->sanitize->sanitizeBool($_POST['deletePluginsAndThemes']);
        // Set default statuses for backup of uploads dir and cleaning of uploads, themes and plugins dirs
        $this->options->statusBackupUploadsDir = 'pending';
        $this->options->statusContentCleaner = 'pending';

        $this->options->excludedDirectories = array_merge($excludedDirectories, $this->options->excludedDirectories);

        // Excluded Files
        $this->options->excludedFiles = apply_filters('wpstg_push_excluded_files', [
            '.htaccess',
            '.DS_Store',
            '*.git',
            '*.svn',
            '*.tmp',
            'desktop.ini',
            '.gitignore',
            '*.log',
            'wp-staging-optimizer.php',
            '.wp-staging',
        ]);

        // Directories to Copy Total
        $this->options->directoriesToCopy = array_merge(
            $this->options->includedDirectories,
            $this->options->extraDirectories
        );

        $this->options->createBackupBeforePushing = isset($_POST["createBackupBeforePushing"]) && $this->sanitize->sanitizeBool($_POST["createBackupBeforePushing"]);


        // Save settings
        $this->saveExcludedDirectories();
        $this->saveSelectedTablesToPush();

        if (isset($this->options->jobIdentifier)) {
            WPStaging::make(AnalyticsStagingPush::class)->enqueueStartEvent($this->options->jobIdentifier, $this->options);
        }

        return $this->saveOptions();
    }

    /**
     * Save excluded directories
     * @return boolean
     */
    private function saveExcludedDirectories()
    {
        if (empty($this->options->existingClones[$this->options->clone])) {
            return false;
        }

        $this->options->existingClones[$this->options->clone]['excludedDirs'] = $this->options->excludedDirectories;

        if (update_option(Sites::STAGING_SITES_OPTION, $this->options->existingClones) === false) {
            return false;
        }

        return true;
    }

    /**
     * Save included, selected tables
     * @return boolean
     */
    private function saveSelectedTablesToPush()
    {
        if (empty($this->options->existingClones[$this->options->clone])) {
            return false;
        }

        $this->options->existingClones[$this->options->clone]['tablePushSelection'] = $this->options->tables;

        if (update_option(Sites::STAGING_SITES_OPTION, $this->options->existingClones) === false) {
            return false;
        }

        return true;
    }

    /**
     * Get prefix of staging site
     * @return string
     */
    private function getPrefix()
    {
        $prefix = 'tmp_';

        if ($this->isExternalDatabase() && isset($this->options->existingClones[$this->options->current]['databasePrefix'])) {
            $prefix = $this->options->existingClones[$this->options->current]['databasePrefix'];
        }

        if (isset($this->options->existingClones[$this->options->clone]['prefix'])) {
            $prefix = $this->options->existingClones[$this->options->clone]['prefix'];
        }

        return $prefix;
    }

    /**
     * @todo Response should be a DTO (we have it now; cloneDTO, perhaps needs extension)
     * @param object $response
     * @param string $nextJob
     *
     * @return object
     */
    private function handleJobResponse($response, $nextJob)
    {
        /*
         * This only fires When creating an automatic database-only backup.
         */
        if ($response instanceof TaskResponseDto) {
            $this->options->currentStep = $response->getStep();
            $this->options->totalSteps = $response->getTotal();
            $this->saveOptions();
            $response = json_decode(json_encode($response), false);
        }

        /**
         * @todo Normalize $response to an object.
         * @see \WPStaging\Backend\Pro\Modules\Jobs\Finish::start
         */
        if (is_array($response) && array_key_exists('status', $response)) {
            return $response;
        }

        if (!isset($response->status)) {
            $response->error = true;
            $response->message = "Response does not have status, therefore we can't detect whether it finished or not.";

            return $response;
        }

        // Job is not done. Status true means the process is finished
        // TODO Ref: $response->isFinished instead of $response->status; self explanatory hence no comment like above
        if ($response->status !== true) {
            return $response;
        }

        $this->options->currentJob = $nextJob;
        $this->options->currentStep = 0;
        $this->options->totalSteps = 0;

        // Save options
        $this->saveOptions();

        return $response;
    }

    public function jobDeleteOldBackups()
    {
        // Early bail: Not doing a backup. Skipping...
        if (!$this->options->createBackupBeforePushing) {
            return $this->jobFileScanning();
        }

        // We dont have backup feature for subsites yet. This will stop subsite to create automated backup
        if (is_multisite() && !is_main_site()) {
            $this->log(__("Skip creating database backup, as feature for backup of subsite is not implemented yet!", "wp-staging"));
            return $this->jobFileScanning();
        }

        /** @var BackupDeleter */
        $backupDeleter = WPStaging::getInstance()->getContainer()->make(BackupDeleter::class);
        $backupDeleter->deleteAllAutomatedDbOnlyBackups();

        foreach ($backupDeleter->getErrors() as $error) {
            $this->log($error, Logger::TYPE_ERROR);
        }

        $response = new TaskResponseDto();
        $response->setStatus(true);

        return $this->handleJobResponse($response, 'jobPrepareBackup');
    }

    public function jobPrepareBackup()
    {
        /** @var PrepareExport $prepare */
        $prepare  = WPStaging::make(PrepareExport::class);
        $prepared = $prepare->prepare([
            'isExportingDatabase' => true,
            'isAutomatedBackup' => true,
            'storages' => ['localStorage'],
            'Name' => __('Database Backup', 'wp-staging'),
        ]);

        if (is_wp_error($prepared)) {
            throw new \UnexpectedValueException($prepared->get_error_message(), $prepared->get_error_code());
        }

        $response = new TaskResponseDto();
        $response->setStatus(true);

        return $this->handleJobResponse($response, 'jobBackup');
    }

    /**
     * Step 1
     * Take a backup of the production database
     */
    public function jobBackup()
    {
        $job = WPStaging::getInstance()->getContainer()->make(JobExport::class);

        if (!$job) {
            throw new RuntimeException('Failed to get Job Site Export');
        }

        return $this->handleJobResponse($job->prepareAndExecute(), 'jobFileScanning');
    }

    /**
     * Step 2
     * Scan folders for files to copy
     * @return object
     */
    public function jobFileScanning()
    {
        $directories = new ScanDirectories();

        return $this->handleJobResponse($directories->start(), 'jobCopy');
    }

    /**
     * Step 3
     * Copy Files
     * @return object
     */
    public function jobCopy()
    {
        $files = new Files();

        return $this->handleJobResponse($files->start(), 'jobCopyDatabaseTmp');
    }

    /**
     * Step 4
     * Copy Database tables to tmp tables
     * @return object
     */
    public function jobCopyDatabaseTmp()
    {
        $database = new DatabaseTmp();

        return $this->handleJobResponse($database->start(), 'jobSearchReplace');
    }

    /**
     * Step 5
     * Search & Replace
     * @return object
     */
    public function jobSearchReplace()
    {
        $searchReplace = new SearchReplace();

        return $this->handleJobResponse($searchReplace->start(), 'jobData');
    }

    /**
     * Step 6
     * So some data operations
     * @return object
     */
    public function jobData()
    {
        return $this->handleJobResponse((new Data())->start(), 'jobDatabaseRename');
    }

    /**
     * Step 7
     * Switch live and tmp tables
     * @return object
     */
    public function jobDatabaseRename()
    {
        $databaseBackup = new \WPStaging\Backend\Pro\Modules\Jobs\DatabaseTmpRename();

        return $this->handleJobResponse($databaseBackup->start(), 'jobFinish');
    }

    /**
     * Step 8
     * Finish Job
     * @return object
     */
    public function jobFinish()
    {
        $finish = new \WPStaging\Backend\Pro\Modules\Jobs\Finish();

        // Re-generate the token when the Push is complete.
        // Todo: Consider adding a do_action() on jobFinish to hook here.
        // Todo: Inject using DI.
        $accessToken = new AccessToken();
        $accessToken->generateNewToken();

        return $this->handleJobResponse($finish->start(), '');
    }
}
