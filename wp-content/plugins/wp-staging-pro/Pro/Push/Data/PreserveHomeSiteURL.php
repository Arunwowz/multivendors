<?php

namespace WPStaging\Pro\Push\Data;

use WPStaging\Core\Utils\Logger;

class PreserveHomeSiteURL extends OptionsTablePushService
{
    /**
     * @inheritDoc
     */
    protected function processOptionsTable()
    {
        $this->log("Updating $this->tmpOptionsTable siteurl");

        if (!$this->tableExists($this->prodOptionsTable)) {
            return true;
        }

        if (!$this->preserveURL('siteurl')) {
            return false;
        }

        $this->log("Updating $this->tmpOptionsTable home");

        if (!$this->preserveURL('home')) {
            return false;
        }

        return true;
    }

    /**
     * Preserve URL in given option in production site
     *
     * @param string $optionName
     * @return boolean
     */
    private function preserveURL($optionName)
    {
        // Get the url from production site table
        $url = $this->productionDb->get_var("SELECT option_value FROM $this->prodOptionsTable WHERE option_name = '$optionName' ");

        if (!$url) {
            $this->log("Can not get $optionName from $this->prodOptionsTable. Skipping", Logger::TYPE_WARNING);
            return true;
        }

        // Update siteurl
        $result = $this->productionDb->query(
            "UPDATE $this->tmpOptionsTable SET option_value = '" . $url . "' WHERE option_name = '$optionName'"
        );

        if ($result === false) {
            $this->log("Can not update row $optionName in $this->tmpOptionsTable");
            $this->returnException("Can not update row $optionName in $this->tmpOptionsTable - db error: " . $this->productionDb->last_error);
            return false;
        }

        return true;
    }
}
