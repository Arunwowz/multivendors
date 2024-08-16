<?php

namespace WPStaging\Backend\Pro\Upgrade;

use WPStaging\Backend\Optimizer\Optimizer;
use WPStaging\Backend\Pro\Notices\BackupsDifferentPrefixNotice;
use WPStaging\Core\Cron\Cron;
use WPStaging\Core\Utils\Logger;
use WPStaging\Core\Utils\Helper;
use WPStaging\Core\Utils\IISWebConfig;
use WPStaging\Core\Utils\Htaccess;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Staging\Sites;
use WPStaging\Pro\Backup\BackupRepairer;
use WPStaging\Pro\Backup\Service\BackupsFinder;

use function WPStaging\functions\debug_log;

/**
 * Upgrade Class
 * This must be loaded on every page init to ensure all settings are 
 * adjusted correctly and to run any upgrade process if necessary.
 */

class Upgrade
{
    const OPTION_UPGRADE_DATE = 'wpstgpro_upgrade_date';

    const OPTION_INSTALL_DATE = 'wpstgpro_install_date';

    /**
     * Previous Version number
     * @var string 
     */
    private $previousVersion;

    /**
     * Clone data
     * @var object
     */
    private $clones;

    /**
     * Global settings
     * @var object
     */
    private $settings;

    /**
     * Logger
     * @var object
     */
    private $logger;

    /**
     * db object
     * @var object
     */
    private $db;

    /**
     * @var Sites
     */
    private $stagingSitesHelper;

    /**
     * @var BackupRepairer
     */
    private $backupRepairer;

    /**
     * @var BackupsFinder
     */
    private $backupsFinder;

    public function __construct()
    {
        // Previous version
        $this->previousVersion = preg_replace('/[^0-9.].*/', '', get_option('wpstgpro_version'));

        // Options earlier than version 2.0.0
        $this->clones = get_option("wpstg_existing_clones", []);

        // @todo This is problematic as it casts into an object even though it should be an array.
        // Could be the reason for this issue which happened in older versions: https://github.com/wp-staging/wp-staging-pro/issues/1536
        $this->settings = (object) get_option("wpstg_settings", []);

        $this->db = WPStaging::getInstance()->get("wpdb");

        // Logger
        $this->logger = new Logger();

        /** @var Sites */
        $this->stagingSitesHelper = WPStaging::make(Sites::class);

        $this->backupRepairer = WPStaging::make(BackupRepairer::class);

        $this->backupsFinder = WPStaging::make(BackupsFinder::class);
    }

    public function doUpgrade()
    {
        $this->upgrade2_0_3();
        $this->upgrade2_0_4();
        $this->upgrade2_1_7();
        $this->upgrade2_3_6();
        $this->upgrade2_6_5();
        $this->upgrade2_8_6();
        $this->upgrade4_0_3();
        $this->upgrade4_0_5();
        $this->upgrade4_0_6();
        $this->upgrade4_2_1();
        $this->upgrade4_3_1();
        $this->setVersion();
    }

    /**
     * To show notice if backups are created on version less than 4.3.1 but,
     * greater than 4.0.0 and on multisite
     */
    private function upgrade4_3_1()
    {
        if (!is_multisite()) {
            return;
        }

        // Previous version lower than 4.3.1
        if (version_compare($this->previousVersion, '4.3.1', '<') && version_compare($this->previousVersion, '4.0.0', '>=')) {
            /** @var BackupsFinder */
            $backupsFinder = WPStaging::make(BackupsFinder::class);
            $backups = $backupsFinder->findBackups();
            // Only enable the notice if any backup available
            if (count($backups) > 0) {
                (new BackupsDifferentPrefixNotice())->enable();
            }
        }
    }

    /**
     * Upgrade method 2.0.3
     */
    public function upgrade2_0_3()
    {
        // Previous version lower than 2.0.2 or first time installation
        if (version_compare($this->previousVersion, '2.0.2', '<')) {
            $this->initialInstall();
            $this->upgradeClonesBeta();
            $this->upgradeNotices();
        }
    }

    /**
     * Upgrade method 2.0.4
     */
    public function upgrade2_0_4()
    {
        if ($this->previousVersion === false || version_compare($this->previousVersion, '2.0.5', '<')) {

            // Register cron job.
            $cron = new Cron();
            $cron->scheduleEvent();

            // Install Optimizer
            $optimizer = new Optimizer();
            $optimizer->installOptimizer();
        }
    }


    private function upgrade4_2_1()
    {
        $forceUpgrade = false;

        if ($forceUpgrade || (version_compare($this->previousVersion, '4.2.1', '<=') && version_compare($this->previousVersion, '4.1.9', '>='))) {
            $this->addMissingBackupSizeToBackupMetadata();
        }
    }

    private function upgrade4_0_6($forceUpgrade = false)
    {
        $forceUpgrade = false;

        if ($forceUpgrade || version_compare($this->previousVersion, '4.0.6', '<')) {
            $this->upgradeCopyExcludedTablesToTablePushSelection();
        }
    }

    /**
     * Move existing staging sites to new option defined in Sites::STAGING_SITES_OPTION
     */
    private function upgrade4_0_5()
    {
        $this->stagingSitesHelper->addMissingCloneNameUpgradeStructure();
        $this->stagingSitesHelper->upgradeStagingSitesOption();
    }

    /**
     * To show notice if backups are created on version less than 4.0.3 but,
     * greater than 4.0.0
     */
    private function upgrade4_0_3()
    {
        // Previous version lower than 4.0.3
        if (version_compare($this->previousVersion, '4.0.3', '<') && version_compare($this->previousVersion, '4.0.0', '>=')) {
            /** @var BackupsFinder */
            $backupsFinder = WPStaging::make(BackupsFinder::class);
            $backups = $backupsFinder->findBackups();
            // Only enable the notice if any backup available
            if (count($backups) > 0) {
                (new BackupsDifferentPrefixNotice())->enable();
            }
        }
    }

    /**
     * Fix array keys of staging sites
     */
    private function upgrade2_8_6()
    {
        // Previous version lower than 2.8.6
        if (version_compare($this->previousVersion, '2.8.6', '<')) {

            // Current options
            $sites = $this->stagingSitesHelper->tryGettingStagingSites();

            $new = [];

            // Fix keys. Replace white spaces with dash character
            foreach ($sites as $oldKey => $site) {
                $key       = preg_replace("#\W+#", '-', strtolower($oldKey));
                $new[$key] = $sites[$oldKey];
            }

            if (!empty($new)) {
                $this->stagingSitesHelper->updateStagingSites($new);
            }
        }
    }

    private function upgrade2_6_5()
    {
        // Previous version lower than 2.6.5
        if (version_compare($this->previousVersion, '2.6.5', '<')) {
            // Add htaccess to wp staging uploads folder
            $htaccess = new Htaccess();
            $htaccess->create(trailingslashit(WPStaging::getContentDir()) . '.htaccess');
            $htaccess->create(trailingslashit(WPStaging::getContentDir()) . 'logs/.htaccess');

            // Add litespeed htaccess to wp root folder
            if (extension_loaded('litespeed')) {
                $htaccess->createLitespeed(ABSPATH . '.htaccess');
            }

            // create web.config file for IIS in wp staging uploads folder
            $webconfig = new IISWebConfig();
            $webconfig->create(trailingslashit(WPStaging::getContentDir()) . 'web.config');
            $webconfig->create(trailingslashit(WPStaging::getContentDir()) . 'logs/web.config');
        }
    }
    /**
     * Upgrade method 2.1.7
     * Sanitize the clone key value.
     */
    private function upgrade2_1_7()
    {
        if ($this->previousVersion === false || version_compare($this->previousVersion, '2.1.7', '<')) {

            $sites = $this->stagingSitesHelper->tryGettingStagingSites();

            foreach ($sites as $key => $value) {
                unset($sites[$key]);
                $sites[preg_replace("#\W+#", '-', strtolower($key))] = $value;
            }

            if (!empty($sites)) {
                $this->stagingSitesHelper->updateStagingSites($sites);
            }
        }
    }

    /**
     * Upgrade method 2.3.6
     */
    private function upgrade2_3_6()
    {
        // Previous version lower than 2.3.6
        if (version_compare($this->previousVersion, '2.3.6', '<')) {
            $this->upgradeElements();
        }
    }

    /**
     * Add missing elements
     */
    private function upgradeElements()
    {
        // Current options
        $sites = $this->stagingSitesHelper->tryGettingStagingSites();

        if ($sites === false || count($sites) === 0) {
            return;
        }

        // Check if key prefix is missing and add it
        foreach ($sites as $key => $value) {
            if (empty($sites[$key]['directoryName'])) {
                continue;
            }

            !empty($sites[$key]['prefix']) ?
                            $sites[$key]['prefix'] = $value['prefix'] :
                            $sites[$key]['prefix'] = $this->getStagingPrefix($sites[$key]['directoryName']);
        }

        if (!empty($sites)) {
            $this->stagingSitesHelper->updateStagingSites($sites);
        }
    }

    /**
     * Check and return prefix of the staging site
     * @param string $directory
     * @return string
     */
    private function getStagingPrefix($directory)
    {
        // Try to get staging prefix from wp-config.php of staging site
        $path = ABSPATH . $directory . "/wp-config.php";

        if (($content = @file_get_contents($path)) === false) {
            $prefix = "";
        } else {
            // Get prefix from wp-config.php
            preg_match("/table_prefix\s*=\s*'(\w*)';/", $content, $matches);

            $prefix = "";
            if (!empty($matches[1])) {
                $prefix = $matches[1];
            }
        }

        // return result: Check if staging prefix is the same as the live prefix
        if ($this->db->prefix !== $prefix) {
            return $prefix;
        }

        return "";
    }

    /**
     * Upgrade routine for new install
     */
    private function initialInstall()
    {
        // Write some default vars
        add_option('wpstg_installDate', date('Y-m-d h:i:s'));
        add_option(self::OPTION_INSTALL_DATE, date('Y-m-d h:i:s'));
        $this->settings->optimizer = 1;
        update_option('wpstg_settings', $this->settings);
    }

    /**
     * Write new version number into db
     * return bool
     */
    private function setVersion()
    {
        // Check if version number in DB is lower than version number in current plugin or if it contains a deprecated development version number like 2021.07.22.162834673
        if (version_compare($this->previousVersion, WPStaging::getVersion(), '<') || $this->isInvalidVersionNumber()) {
            // Update Version number
            update_option('wpstgpro_version', preg_replace('/[^0-9.].*/', '', WPStaging::getVersion()));
            // Update "upgraded from" version number
            update_option('wpstgpro_version_upgraded_from', preg_replace('/[^0-9.].*/', '', $this->previousVersion));
            // Update date
            update_option(self::OPTION_UPGRADE_DATE, date('Y-m-d H:i'));

            return true;
        }

        return false;
    }

    /**
     * During development, we've added non-semver version numbers like 2021.07.22.162834673
     * These numbers prevent the proper execution of the upgrade process because version_compare() does not work on such numbers.
     * We have to delete and invalidate these non-semver numbers before executing the upgrade process.
     *
     * @return bool
     */
    private function isInvalidVersionNumber(){
        if (strpos($this->previousVersion, '2021', 0) === 0) {
            return true;
        }
        return false;
    }

    /**
     * Create a new db option for beta version 2.0.2
     */
    private function upgradeClonesBeta()
    {
        if (empty($this->clones) || count($this->clones) === 0) {
            return;
        }

        $existingClones = $this->stagingSitesHelper->tryGettingStagingSites();
        $helper         = new Helper();

        // Add old clones to existing clones
        foreach ($this->clones as $key => &$value) {

            // Skip the rest of the loop if data is already compatible to wpstg 2.0.2
            if (isset($value['directoryName']) || !empty($value['directoryName'])) {
                continue;
            }

            // Skip if clone is already copied
            if (isset($existingClones[$value])) {
                continue;
            }

            $existingClones[$value]['directoryName'] = $value;
            $existingClones[$value]['path']          = get_home_path() . $value;
            $existingClones[$value]['url']           = $helper->getHomeUrl() . "/" . $value;
            $existingClones[$value]['number']        = $key + 1;
            $existingClones[$value]['version']       = $this->previousVersion;
        }

        unset($value);

        if (empty($existingClones) || $this->stagingSitesHelper->updateStagingSites($existingClones) === false) {
            $this->logger->log('INFO', 'Failed to upgrade clone data from ' . $this->previousVersion . ' to ' . WPStaging::getVersion());
        }
    }

    /**
     * Upgrade Notices db options from wpstg 1.3 -> 2.0.1
     * Fix some logical db options
     */
    private function upgradeNotices()
    {
        $poll   = get_option("wpstg_start_poll", false);
        $beta   = get_option("wpstg_hide_beta", false);
        $rating = get_option("wpstg_RatingDiv", false);

        if ($beta && $beta === "yes") {
            update_option('wpstg_beta', 'no');
        }

        if ($rating && $rating === 'yes') {
            update_option('wpstg_rating', 'no');
        }
    }

    /**
     * Copy existing data from wp_option [wpstg_staging_sites]->excludedTables to
     * new option [wpstg_staging_sites]->tablePushSelection
     *
     * @see \WPStaging\Backend\Pro\Upgrade\Upgrade::upgrade4_0_6 (Pro version)
     */
    private function upgradeCopyExcludedTablesToTablePushSelection()
    {
        $sites = get_option(Sites::STAGING_SITES_OPTION, []);

        if (empty($sites)) {
            return;
        }

        // For extra safety only upgrade tablePushSelection if it's not existent or empty
        foreach ($sites as &$value) {
            if (!empty($value['excludedTables']) && empty($value['tablePushSelection'])) {
                $value['tablePushSelection'] = $value['excludedTables'];
            }
        }

        if (update_option(Sites::STAGING_SITES_OPTION, $sites)) {
           // Create a backup just in case
           update_option(Sites::BACKUP_STAGING_SITES_OPTION, $sites, false);
        }
    }

    /**
     * Add missing backup size in backup metadata for backup created on version 4.1.9 and 4.2.0
     */
    private function addMissingBackupSizeToBackupMetadata()
    {
        foreach ($this->backupsFinder->findBackups() as $backup) {
            if (!$this->backupRepairer->repairMetadataSize($backup->getRealPath())) {
                $error = $this->backupRepairer->getError();
                debug_log('Error in WP STAGING upgrade routine affects Backup ' . $backup->getRealPath() . ' - ' . $error, 'error');
            }
        }
    }
}
