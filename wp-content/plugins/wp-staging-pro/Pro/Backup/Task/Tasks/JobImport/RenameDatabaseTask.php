<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobImport;

use WPStaging\Framework\Database\TableService;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Framework\Security\AccessToken;
use WPStaging\Framework\Staging\Sites;
use WPStaging\Framework\Utils\Cache\Cache;
use WPStaging\Pro\Backup\Ajax\Import\PrepareImport;
use WPStaging\Pro\Backup\BackupScheduler;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Service\Database\Exporter\ViewDDLOrder;
use WPStaging\Pro\Backup\Service\Database\Importer\TableViewsRenamer;
use WPStaging\Pro\Backup\Task\ImportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

class RenameDatabaseTask extends ImportTask
{
    private $tableService;

    private $tableViewsRenamer;

    private $accessToken;

    // eg: ['wp123456_options']
    protected $tablesBeingImported = [];

    // eg: ['options']
    protected $tablesBeingImportedUnprefixed = [];

    // eg: ['wp_options']
    protected $existingTables = [];

    // eg: ['options']
    protected $existingTablesUnprefixed = [];

    /** @var int How many new tables were imported */
    protected $newTablesImported = 0;

    /** @var array An structured array of options to keep */
    protected $optionsToKeep = [];

    protected $viewDDLOrder;

    public function __construct(ViewDDLOrder $viewDDLOrder, TableService $tableService, TableViewsRenamer $tableViewsRenamer, AccessToken $accessToken, LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);
        $this->tableService = $tableService;
        $this->accessToken = $accessToken;
        $this->tableViewsRenamer = $tableViewsRenamer;
        $this->viewDDLOrder = $viewDDLOrder;
    }

    public static function getTaskName()
    {
        return 'backup_restore_rename_database';
    }

    public static function getTaskTitle()
    {
        return 'Renaming Database Tables';
    }

    public function execute()
    {
        $this->stepsDto->setTotal(1);

        if ($this->jobDataDto->getIsMissingDatabaseFile()) {
            $this->logger->warning(__('Skipped importing database.', 'wp-staging'));
            return $this->generateResponse();
        }

        // Store some information to re-add after we import the database.
        $originalAccessToken = $this->accessToken->getToken();
        $originalIsPluginActiveForNetwork = is_plugin_active_for_network(WPSTG_PLUGIN_FILE);

        $this->keepOptions();

        $this->tablesBeingImported = [
            'views' => $this->tableService->findViewsNamesStartWith($this->jobDataDto->getTmpDatabasePrefix()) ?: [],
            'tables' => $this->tableService->findTableNamesStartWith($this->jobDataDto->getTmpDatabasePrefix()) ?: [],
        ];
        $this->tablesBeingImported['all'] = array_merge($this->tablesBeingImported['tables'], $this->tablesBeingImported['views']);

        // Make a copy of the array of tables being imported, without the prefix.

        foreach ($this->tablesBeingImported as $viewsOrTables => $tableName) {
            $this->tablesBeingImportedUnprefixed[$viewsOrTables] = array_map(function ($tableName) {
                $tableName = $this->getFullNameTableFromShortName($tableName, PrepareImport::TMP_DATABASE_PREFIX);
                return substr($tableName, strlen($this->jobDataDto->getTmpDatabasePrefix()));
            }, $this->tablesBeingImported[$viewsOrTables]);
        }

        $this->existingTables = [
            'views' => $this->tableService->findViewsNamesStartWith($this->tableService->getDatabase()->getPrefix()) ?: [],
            'tables' => $this->tableService->findTableNamesStartWith($this->tableService->getDatabase()->getPrefix()) ?: [],
        ];
        $this->existingTables['all'] = array_merge($this->existingTables['tables'], $this->existingTables['views']);

        // Make a copy of the array of existing tables, without the prefix.
        foreach ($this->existingTables as $viewsOrTables => $tableName) {
            $this->existingTablesUnprefixed[$viewsOrTables] = array_map(function ($tableName) {
                return substr($tableName, strlen($this->tableService->getDatabase()->getPrefix()));
            }, $this->existingTables[$viewsOrTables]);
        }

        $this->renameConflictingTables();
        $this->renameNonConflictingTables();
        $this->renameViewReferences();

        foreach ($this->getTablesThatExistInSiteButNotInBackup() as $table) {
            $fullTable = $this->tableService->getDatabase()->getPrefix() . $table;
            $tableToDrop = $this->getShortNameTable($fullTable, PrepareImport::TMP_DATABASE_PREFIX_TO_DROP);
            if ($tableToDrop === false) {
                $tableToDrop = PrepareImport::TMP_DATABASE_PREFIX_TO_DROP . $table;
            }

            $this->tableService->getDatabase()->exec(sprintf(
                "RENAME TABLE `%s` TO `%s`;",
                $fullTable,
                $tableToDrop
            ));
        }

        $this->logger->info(sprintf('Imported %d/%d new tables', $this->newTablesImported, $this->newTablesImported));

        // Reset cache
        wp_cache_init();

        $this->postDatabaseRestoreActions($originalAccessToken, $originalIsPluginActiveForNetwork);

        // Logs the user out
        wp_logout();

        return $this->generateResponse();
    }

    /**
     * This is an adaptation of wp_load_alloptions(), the difference is that it
     * fetches only the "option_name" from the database, not the values, to save memory.
     *
     * @return array An array of option names that are autoloaded.
     * @see wp_load_alloptions()
     *
     */
    protected function getAutoloadedOptions()
    {
        global $wpdb;
        $suppress = $wpdb->suppress_errors();
        $allOptionsDb = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE autoload = 'yes'");
        $wpdb->suppress_errors($suppress);

        $allOptions = [];
        foreach ((array)$allOptionsDb as $o) {
            $allOptions[] = $o->option_name;
        }

        return $allOptions;
    }

    protected function keepOptions()
    {
        $allOptions = $this->getAutoloadedOptions();

        // Backups do not include staging sites, so we need to keep the original ones after restoring.
        // For version 2.x to 4.0.2
        $this->optionsToKeep[] = [
            'name' => 'wpstg_existing_clones_beta',
            'value' => get_option('wpstg_existing_clones_beta'),
            'autoload' => in_array('wpstg_existing_clones_beta', $allOptions),
        ];

        // For version > 4.0.3
        $this->optionsToKeep[] = [
            'name' => Sites::STAGING_SITES_OPTION,
            'value' => get_option(Sites::STAGING_SITES_OPTION),
            'autoload' => in_array(Sites::STAGING_SITES_OPTION, $allOptions),
        ];

        // Keep the original WP STAGING settings intact upon importing.
        $this->optionsToKeep[] = [
            'name' => 'wpstg_settings',
            'value' => get_option('wpstg_settings'),
            'autoload' => in_array('wpstg_settings', $allOptions),
        ];

        // If this is a staging site, keep the staging site status after restore.
        $this->optionsToKeep[] = [
            'name' => 'wpstg_is_staging_site',
            'value' => get_option('wpstg_is_staging_site'),
            'autoload' => in_array('wpstg_is_staging_site', $allOptions),
        ];

        // Preserve backup schedules
        $this->optionsToKeep[] = [
            'name' => BackupScheduler::OPTION_BACKUP_SCHEDULES,
            'value' => get_option(BackupScheduler::OPTION_BACKUP_SCHEDULES),
            'autoload' => in_array(BackupScheduler::OPTION_BACKUP_SCHEDULES, $allOptions),
        ];

        // Preserve existing blog_public value.
        $this->optionsToKeep[] = [
            'name' => 'blog_public',
            'value' => get_option('blog_public'),
            'autoload' => in_array('blog_public', $allOptions),
        ];

        global $wpdb;

        $analyticsEvents = $wpdb->get_results("SELECT * FROM $wpdb->options WHERE `option_name` LIKE 'wpstg_analytics_event_%' LIMIT 0, 200");

        if (!empty($analyticsEvents)) {
            foreach ($analyticsEvents as $option) {
                $this->optionsToKeep[] = [
                    'name' => $option->option_name,
                    'value' => $option->option_value,
                    'autoload' => false,
                ];
            }
        }
    }

    /**
     * Executes actions after a database has been restored.
     *
     * @param $originalAccessToken
     * @param $originalIsPluginActiveForNetwork
     */
    protected function postDatabaseRestoreActions($originalAccessToken, $originalIsPluginActiveForNetwork)
    {
        /**
         * @var \wpdb $wpdb
         * @var \WP_Object_Cache $wp_object_cache
         */
        global $wpdb, $wp_object_cache;

        // Make sure WordPress does not try to re-use any values fetched from the database thus far.
        $wpdb->flush();
        $wp_object_cache->flush();
        wp_suspend_cache_addition(true);

        // Prevent filters tampering with the active plugins list, such as wpstg-optimizer.php itself.
        remove_all_filters('option_active_plugins');
        remove_all_filters('site_option_active_sitewide_plugins');

        foreach ($this->optionsToKeep as $optionToKeep) {
            update_option($optionToKeep['name'], $optionToKeep['value'], $optionToKeep['autoload']);
        }

        update_option('wpstg.restore.justRestored', 'yes');
        update_option('wpstg.restore.justRestored.metadata', wp_json_encode($this->jobDataDto->getBackupMetadata()));

        // Re-set the Access Token as it was before importing the database, so the requests remain authenticated
        $this->accessToken->setToken($originalAccessToken);

        // Force direct activation of this plugin in the database by bypassing activate_plugin at a low-level.
        $plugin = plugin_basename(trim(WPSTG_PLUGIN_FILE));

        if ($originalIsPluginActiveForNetwork) {
            $current = get_site_option('active_sitewide_plugins', []);
            $current[$plugin] = time();
            update_site_option('active_sitewide_plugins', $current);
        } else {
            $current = get_option('active_plugins', []);

            // Disable all other WPSTAGING plugins
            $current = array_filter($current, function ($pluginSlug) {
                return strpos($pluginSlug, 'wp-staging') === false;
            });

            // Enable this plugin
            $current[] = $plugin;

            sort($current);
            update_option('active_plugins', $current);
        }

        // Upgrade database if need be
        if (file_exists(trailingslashit(ABSPATH) . 'wp-admin/includes/upgrade.php')) {
            global $wpdb, $wp_db_version, $wp_current_db_version;
            require_once trailingslashit(ABSPATH) . 'wp-admin/includes/upgrade.php';

            $wp_current_db_version = (int)__get_option('db_version');
            if ($wp_db_version !== $wp_current_db_version) {
                // WP upgrade isn't too fussy about generating MySQL warnings such as "Duplicate key name" during an upgrade so suppress.
                $wpdb->suppress_errors();

                wp_upgrade();

                $this->logger->info(sprintf('WordPress database upgraded successfully from db version %s to %s.', $wp_current_db_version, $wp_db_version));
            }
        } else {
            $this->logger->warning('Could not upgrade WordPress database version as the wp-admin/includes/upgrade.php file does not exist.');
        }

        do_action('wpstg.backup.import.database.postDatabaseRestoreActions');
    }

    protected function renameConflictingTables()
    {
        $this->tableService->getDatabase()->exec('START TRANSACTION;');

        foreach ($this->getTablesThatExistInSiteAndInBackup() as $conflictingTable) {
            if ($this->isExcludedTable($conflictingTable)) {
                $this->newTablesImported++;
                continue;
            }

            $currentTable = $this->tableService->getDatabase()->getPrefix() . $conflictingTable;
            $tableToDrop =  $this->getShortNameTable($currentTable, PrepareImport::TMP_DATABASE_PREFIX_TO_DROP);
            if ($tableToDrop === false) {
                $tableToDrop = PrepareImport::TMP_DATABASE_PREFIX_TO_DROP . $conflictingTable;
            }

            // Prefix existing table with toDrop prefix
            $this->tableService->getDatabase()->exec(sprintf(
                "RENAME TABLE `%s` TO `%s`;",
                $currentTable,
                $tableToDrop
            ));

            $tableToImport = $this->jobDataDto->getTmpDatabasePrefix() . $conflictingTable;
            $tmpName = $this->getShortNameTable($tableToImport, PrepareImport::TMP_DATABASE_PREFIX);
            if ($tmpName !== false) {
                $tableToImport = $tmpName;
            }

            // Rename imported table to existing
            $this->tableService->getDatabase()->exec(sprintf(
                "RENAME TABLE `%s` TO `%s`;",
                $tableToImport,
                $this->tableService->getDatabase()->getPrefix() . $conflictingTable
            ));

            $this->newTablesImported++;
        }
        $this->tableService->getDatabase()->exec('COMMIT;');
    }

    protected function renameNonConflictingTables()
    {
        $this->tableService->getDatabase()->exec('START TRANSACTION;');
        foreach ($this->getTablesThatExistInBackupButNotInSite() as $nonConflictingTable) {
            if ($this->isExcludedTable($nonConflictingTable)) {
                continue;
            }

            $tableToImport = $this->jobDataDto->getTmpDatabasePrefix() . $nonConflictingTable;
            $tmpName = $this->getShortNameTable($tableToImport, PrepareImport::TMP_DATABASE_PREFIX);
            if ($tmpName !== false) {
                $tableToImport = $tmpName;
            }

            // Rename imported table to original
            $this->tableService->getDatabase()->exec(sprintf(
                "RENAME TABLE `%s` TO `%s`;",
                $tableToImport,
                $this->tableService->getDatabase()->getPrefix() . $nonConflictingTable
            ));
            $this->newTablesImported++;
        }
        $this->tableService->getDatabase()->exec('COMMIT;');
    }

    protected function isExcludedTable($tableName)
    {
        // Excluded table names without prefix
        $excludedTables = [
            'wpstg_queue'
        ];

        if (in_array($tableName, $excludedTables)) {
            return true;
        }
        return false;
    }

    protected function renameViewReferences()
    {
        foreach ($this->tablesBeingImportedUnprefixed['views'] as $view) {
            $query = $this->tableService->getCreateViewQuery($this->tableService->getDatabase()->getPrefix() . $view);
            $query = str_replace($this->jobDataDto->getTmpDatabasePrefix(), $this->tableService->getDatabase()->getPrefix(), $query);
            $this->viewDDLOrder->enqueueViewToBeWritten($this->tableService->getDatabase()->getPrefix() . $view, $query);
        }

        foreach ($this->viewDDLOrder->tryGetOrderedViews() as $tmpViewName => $viewQuery) {
            $this->tableViewsRenamer->renameViewReferences($viewQuery);
        }
    }

    protected function getTablesThatExistInSiteAndInBackup()
    {
        return array_intersect($this->tablesBeingImportedUnprefixed['all'], $this->existingTablesUnprefixed['all']);
    }

    protected function getTablesThatExistInSiteButNotInBackup()
    {
        return array_diff($this->existingTablesUnprefixed['all'], $this->tablesBeingImportedUnprefixed['all']);
    }

    protected function getTablesThatExistInBackupButNotInSite()
    {
        return array_diff($this->tablesBeingImportedUnprefixed['all'], $this->existingTablesUnprefixed['all']);
    }
}
