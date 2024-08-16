<?php

namespace WPStaging\Pro\Push\Data;

use WPStaging\Core\Utils\Logger;

class PreserveBlogPublicSettings extends OptionsTablePushService
{
    /**
     * @inheritDoc
     */
    protected function processOptionsTable()
    {
        $this->log("Copy blog_public from production site to $this->tmpOptionsTable");

        if (!$this->tableExists($this->prodOptionsTable)) {
            return true;
        }

        // Get blog_public value from live site options table
        $result = $this->productionDb->get_var("SELECT option_value FROM $this->prodOptionsTable WHERE option_name = 'blog_public' ");

        if (!$result) {
            $this->log("Can not find blog_public in $this->prodOptionsTable", Logger::TYPE_WARNING);
            //$this->returnException("Can not get blog_public in {$this->productionDb->prefix}options");
            return true;
        }

        // Update blog_public
        $update = $this->productionDb->query(
            "UPDATE $this->tmpOptionsTable SET option_value = '" . $result . "' WHERE option_name = 'blog_public'"
        );

        if ($update === false) {
            $this->log("Can not update row blog_public in $this->tmpOptionsTable", Logger::TYPE_WARNING);
            return true;
        }

        return true;
    }
}
