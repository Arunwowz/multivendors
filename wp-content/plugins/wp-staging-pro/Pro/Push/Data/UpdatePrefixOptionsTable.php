<?php

namespace WPStaging\Pro\Push\Data;

class UpdatePrefixOptionsTable extends OptionsTablePushService
{
    /**
     * @inheritDoc
     */
    protected function processOptionsTable()
    {
        $this->log("Updating {$this->tmpOptionsTable} table prefix to {$this->productionDb->prefix}.");
        $this->debugLog("SQL - UPDATE {$this->tmpOptionsTable} SET option_name = replace(option_name, {$this->stagingPrefix}, {$this->productionDb->prefix}) WHERE option_name LIKE {$this->stagingPrefix}_%");

        $resultOptions = $this->productionDb->query(
            $this->productionDb->prepare(
                // db_version is a wp option_name and should be excluded in case staging db prefix is 'db_'
                "UPDATE IGNORE {$this->tmpOptionsTable} SET option_name= replace(option_name, %s, %s) WHERE option_name LIKE %s AND option_name <> 'db_version'",
                $this->stagingPrefix,
                $this->productionDb->prefix,
                $this->stagingPrefix . "_%"
            )
        );

        if ($resultOptions === false) {
            $this->log("Failed to update {$this->tmpOptionsTable} with table prefixes. DB Error: {$this->productionDb->last_error}");
            $this->returnException("Failed to update {$this->tmpOptionsTable} with table prefixes {$this->productionDb->prefix}. DB Error: {$this->productionDb->last_error}");
            return false;
        }

        return true;
    }
}
