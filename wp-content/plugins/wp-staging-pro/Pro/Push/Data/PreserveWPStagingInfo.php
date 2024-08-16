<?php

namespace WPStaging\Pro\Push\Data;

use WPStaging\Backend\Notices\DisabledItemsNotice;
use WPStaging\Core\Utils\Logger;
use WPStaging\Framework\SiteInfo;
use WPStaging\Framework\Staging\CloneOptions;
use WPStaging\Framework\Staging\FirstRun;
use WPStaging\Framework\Staging\Sites;

class PreserveWPStagingInfo extends OptionsTablePushService
{
    /**
     * @inheritDoc
     */
    protected function processOptionsTable()
    {
        if (!$this->preserveAnalyticsEvents()) {
            \WPStaging\functions\debug_log('WP STAGING Analytics: Couldn\'t preserve analytics events on PreserveWPStagingInfo class');
        }

        if (!$this->preserveStagingSites()) {
            return false;
        }

        if (!$this->removeStagingInfo()) {
            return false;
        }

        // Copy license data
        $resultWpstgLicense = $this->preserveOption('wpstg_license_key');
        if ($resultWpstgLicense === false) {
            $this->log('Can not copy license key from live site', Logger::TYPE_WARNING);
        }

        // Copy license status
        $resultWpstgLicenseStatus = $this->preserveOption('wpstg_license_status');
        if ($resultWpstgLicenseStatus === false) {
            $this->log('Can not copy license status from live site', Logger::TYPE_WARNING);
        }

        // Copy wpstg settings
        $resultWpstgSettings = $this->preserveOption('wpstg_settings');
        if ($resultWpstgSettings === false) {
            $this->log('Can not copy wpstg settings from live site', Logger::TYPE_WARNING);
        }

        return true;
    }

    protected function preserveAnalyticsEvents()
    {
        if (!$this->tableExists($this->prodOptionsTable)) {
            return true;
        }

        // Preserve Analytics Events
        $analyticsEvents = $this->productionDb->get_results("SELECT * FROM $this->prodOptionsTable WHERE `option_name` LIKE 'wpstg_analytics_event_%' LIMIT 0, 200");

        $hasFailure = false;

        foreach ($analyticsEvents as $event) {
            $result = $this->productionDb->replace($this->tmpOptionsTable, [
                'option_name' => $event->option_name,
                'option_value' => $event->option_value,
            ]);

            if (!$result) {
                $hasFailure = true;
                $this->log('DB Data: Warning: Can not copy WP Staging analytics events from live site', Logger::TYPE_WARNING);
            }
        }

        if ($hasFailure) {
            return false;
        }

        return true;
    }

    /**
     * Preserve Production Site Staging Sites
     *
     * @return boolean
     */
    private function preserveStagingSites()
    {
        $this->log("Move list of staging sites to " . $this->tmpOptionsTable);

        // Get staging sites data from live site - WP Staging 2.0 and higher
        $stagingSitesOption = Sites::STAGING_SITES_OPTION;

        if (!$this->tableExists($this->prodOptionsTable)) {
            return true;
        }

        $wpstgStagingSites = $this->productionDb->get_var("SELECT option_value FROM $this->prodOptionsTable WHERE option_name = '$stagingSitesOption' ");

        if (!$wpstgStagingSites) {
            $this->log("Can not get data wpstg_staging_sites from " . $this->tmpOptionsTable . ". Skipping", Logger::TYPE_WARNING);
            return true;
        }

        // do some escaping
        $serialized = $this->mysqlRealEscapeString($wpstgStagingSites);

        $query = $this->productionDb->query(
            "INSERT INTO $this->tmpOptionsTable (option_name, option_value) VALUES ('$stagingSitesOption', '$serialized') ON DUPLICATE KEY UPDATE option_value = '$serialized'"
        );

        if ($query === false) {
            $this->log("Can not insert/update value $stagingSitesOption in " . $this->tmpOptionsTable . ' - db error: ' . $this->productionDb->last_error);
            $this->returnException("Can not insert/update value $stagingSitesOption in " . $this->tmpOptionsTable . ' - db error: ' . $this->productionDb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Remove Staging site related data
     *
     * @return boolean
     */
    private function removeStagingInfo()
    {
        $optionsToDelete = (array)apply_filters('wpstg.after_push.options_to_delete', [
            DisabledItemsNotice::OPTION_NAME,
            FirstRun::FIRST_RUN_KEY,
            FirstRun::MAILS_DISABLED_KEY,
        ]);

        // DELETE these option only if current site is production site
        if (!(new SiteInfo())->isStagingSite()) {
            $optionsToDelete[] = SiteInfo::IS_STAGING_KEY;
            $optionsToDelete[] = CloneOptions::WPSTG_CLONE_SETTINGS_KEY;
        }

        foreach ($optionsToDelete as $option) {
            $resultDelete = $this->productionDb->query(
                "DELETE FROM $this->tmpOptionsTable WHERE option_name = '$option'"
            );

            if ($resultDelete === false) {
                $this->log("Can not delete table row $option from $this->tmpOptionsTable");
                $this->returnException("Can not delete table row $option from $this->tmpOptionsTable - db error: " . $this->productionDb->last_error);
                return false;
            }
        }

        return true;
    }

    /**
     * Preserve Production Site options
     *
     * @param string $optionName
     * @return boolean
     */
    private function preserveOption($optionName)
    {

        if (!$this->tableExists($this->prodOptionsTable)) {
            return true;
        }

        $optionValue = $this->productionDb->get_var("SELECT option_value FROM $this->prodOptionsTable WHERE option_name = '$optionName' ");

        return $this->productionDb->replace(
            $this->tmpOptionsTable,
            [
                'option_name'  => $optionName,
                'option_value' => $optionValue ?: ''
            ],
            [
                '%s',
                '%s'
            ]
        );
    }
}
