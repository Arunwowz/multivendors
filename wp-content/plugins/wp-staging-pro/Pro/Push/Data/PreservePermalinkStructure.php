<?php

namespace WPStaging\Pro\Push\Data;

class PreservePermalinkStructure extends OptionsTablePushService
{
    /**
     * @inheritDoc
     */
    protected function processOptionsTable()
    {
        if (!$this->tableExists($this->prodOptionsTable)) {
            return true;
        }

        $this->log("Updating $this->tmpOptionsTable permalink_structure");

        // Get permalink_structure value from live site options table
        $permalink = $this->productionDb->get_var("SELECT option_value FROM $this->prodOptionsTable WHERE option_name = 'permalink_structure' ");

        if (!$permalink) {
            $this->log("Can not get permalink_structure from $this->prodOptionsTable");
            $permalink = '/%postname%/';
        }

        // Update permalink_structure
        $resultPermalink = $this->productionDb->query(
            "UPDATE $this->tmpOptionsTable SET option_value = '" . $permalink . "' WHERE option_name = 'permalink_structure'"
        );

        if ($resultPermalink === false) {
            $this->log("Can not update row permalink_structure in $this->tmpOptionsTable");
            $this->returnException("Can not update row permalink_structure in $this->tmpOptionsTable - db error: " . $this->productionDb->last_error);
            return false;
        }

        return true;
    }
}
