<?php

namespace WPStaging\Pro\Push\Data;

use WPStaging\Core\Utils\Logger;

class PreserveWPStagingProVersion extends OptionsTablePushService
{
    /**
     * @inheritDoc
     */
    protected function processOptionsTable()
    {
        $this->log("Updating $this->tmpOptionsTable wpstgpro_version");

        if (!$this->tableExists($this->prodOptionsTable)) {
            return true;
        }

        // Get wpstgpro_version value from live site {$tableName} table
        $select = $this->productionDb->get_var("SELECT option_value FROM $this->prodOptionsTable WHERE option_name = 'wpstgpro_version' ");

        if (!$select) {
            $this->log("Can not get wpstgpro_version from $this->prodOptionsTable", Logger::TYPE_WARNING);
            return true;
            // $this->returnException("Can not find wpstgpro_version in {$this->prodOptionsTable}");
        }

        // Update wpstgpro_version
        $update = $this->productionDb->query(
            "UPDATE $this->tmpOptionsTable SET option_value = '" . $select . "' WHERE option_name = 'wpstgpro_version'"
        );

        if ($update === false) {
            $this->log("Can not update row wpstgpro_version in $this->tmpOptionsTable");
            $this->returnException("Can not update row wpstgpro_version in $this->tmpOptionsTable - db error: " . $this->productionDb->last_error);
            return false;
        }

        return true;
    }
}
