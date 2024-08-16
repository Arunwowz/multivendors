<?php

namespace WPStaging\Pro\Push\Data;

use WPStaging\Framework\Security\AccessToken;
use WPStaging\Framework\SiteInfo;
use WPStaging\Framework\Staging\CloneOptions;
use WPStaging\Framework\Support\ThirdParty\FreemiusScript;
use WPStaging\Pro\Backup\BackupScheduler;

class PreserveOptions extends OptionsTablePushService
{
    /**
     * @inheritDoc
     */
    protected function processOptionsTable()
    {
        $this->log("Preserve Data in " . $this->prodOptionsTable);

        if (!$this->tableExists($this->prodOptionsTable)) {
            return true;
        }

        $sql = '';

        $optionsToPreserve = [
            'wpstg_optimizer_excluded',
            'wpstg_version_upgraded_from',
            'wpstg_version',
            'wpstg_installDate',
            'wpstg_free_install_date',
            'wpstgpro_install_date',
            'wpstgpro_upgrade_date',
            'wpstgpro_version',
            'wpstgpro_version_upgraded_from',
            'wpstg_version_latest',
            'wpstg_queue_table_version',
            'upload_path',
            'wpstg_free_upgrade_date',
            'wpstg_googledrive',
            'wpstg_amazons3',
            'wpstg_sftp',
            'wpstg_digitalocean',
            'wpstg_wasabi',
            BackupScheduler::OPTION_BACKUP_SCHEDULES,
            AccessToken::OPTION_NAME
        ];

        // Preserve CloneOptions if current site is staging site
        if ((new SiteInfo())->isStagingSite()) {
            $optionsToPreserve[] = CloneOptions::WPSTG_CLONE_SETTINGS_KEY;
        }

        $freemiusHelper = new FreemiusScript();
        // Preserve freemius options on the production site if present.
        if ($freemiusHelper->hasFreemiusOptions()) {
            $optionsToPreserve = array_merge($optionsToPreserve, $freemiusHelper->getFreemiusOptions());
        }

        $optionsToPreserve        = apply_filters('wpstg_preserved_options', $optionsToPreserve);
        $optionsToPreserveEscaped = esc_sql($optionsToPreserve);

        // Get preserved data in wp_options tables
        $productionOptions = $this->productionDb->get_results(
            sprintf(
                "SELECT * FROM `$this->prodOptionsTable` WHERE `option_name` IN ('%s')",
                implode("','", $optionsToPreserveEscaped)
            ),
            ARRAY_A
        );

        if (empty($productionOptions)) {
            return true;
        }

        $tmpTable = $this->tmpOptionsTable;

        // Delete staging values
        $result = $this->productionDb->query(
            sprintf(
                "DELETE FROM `$tmpTable` WHERE `option_name` IN ('%s');",
                implode("','", $optionsToPreserveEscaped)
            )
        );

        // Create preserved data queries for options tables
        foreach ($productionOptions as $option) {
            $sql .= $this->productionDb->prepare(
                "INSERT INTO `$tmpTable` ( `option_id`, `option_name`, `option_value`, `autoload` ) VALUES ( NULL , %s, %s, %s );\n",
                $option['option_name'],
                $option['option_value'],
                $option['autoload']
            );
        }

        $this->debugLog("Preserve values " . json_encode($productionOptions));

        $this->executeSql($sql);

        return true;
    }
}
