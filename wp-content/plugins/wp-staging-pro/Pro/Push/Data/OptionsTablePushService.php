<?php

namespace WPStaging\Pro\Push\Data;

abstract class OptionsTablePushService extends DBPushService
{
    /**
     * Temporary Options Table
     *
     * @var string
     */
    protected $tmpOptionsTable;

    /**
     * Productions Site Options Table
     *
     * @var string
     */
    protected $prodOptionsTable;

    /**
     * @inheritDoc
     */
    protected function internalExecute()
    {
        if ($this->isNetworkClone()) {
            return $this->updateAllOptionsTables();
        }

        return $this->updateOptionsTable();
    }

    /**
     * Update all options table for entire multisite clone
     *
     * @return boolean
     */
    protected function updateAllOptionsTables()
    {
        foreach ($this->getStagingMultisiteBlogs() as $site) {
            if (!$this->updateOptionsTable($this->getOptionTableWithoutBasePrefix($site->blog_id))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Update the given option table
     *
     * @param string $tableName
     * @return boolean
     */
    protected function updateOptionsTable($tableName = 'options')
    {
        // options table has been exluded from pushing process so exit here
        if ($this->isTableExcluded($this->stagingPrefix . $tableName)) {
            $this->log("$this->stagingPrefix$tableName excluded. Skipping this step");
            return true;
        }

        $this->tmpOptionsTable = $this->getTmpPrefix() . $tableName;

        if ($this->isTable($this->tmpOptionsTable) === false) {
            $this->log('Fatal Error: ' . $this->tmpOptionsTable . ' does not exist');
            $this->returnException('Fatal Error: ' . $this->tmpOptionsTable . ' does not exist');
            return false;
        }

        $this->prodOptionsTable = $this->productionDb->prefix . $tableName;

        return $this->processOptionsTable();
    }

    /**
     * @return boolean
     */
    abstract protected function processOptionsTable();
}
