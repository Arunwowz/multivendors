<?php

namespace WPStaging\Pro\Push\Data;

class RemoveStagingOptions extends OptionsTablePushService
{
    /**
     * @inheritDoc
     */
    protected function processOptionsTable()
    {
        $this->log("Remove staging site specific options from $this->tmpOptionsTable");

        $sql = $this->productionDb->prepare(
            "DELETE FROM `$this->tmpOptionsTable` WHERE `option_name` = %s;\n",
            'wpstg_connection'
        );

        $sql .= $this->productionDb->prepare(
            "DELETE FROM `$this->tmpOptionsTable` WHERE `option_name` = %s;\n",
            'wpstg_emails_disabled'
        );

        $sql .= $this->productionDb->prepare(
            "DELETE FROM `$this->tmpOptionsTable` WHERE `option_name` = %s;\n",
            'wpstg_entire_network_clone_notice'
        );

        /*
         * Prevent Staging Site created before WPSTAGING Pro 4.0.5
         * from re-inserting the old staging sites option on Push.
         */
        $sql .= $this->productionDb->prepare(
            "DELETE FROM `$this->tmpOptionsTable` WHERE `option_name` = %s;\n",
            'wpstg_existing_clones_beta'
        );

        $this->executeSql($sql);

        return true;
    }
}
