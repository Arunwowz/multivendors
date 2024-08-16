<?php

namespace WPStaging\Backend\Pro\Modules\Jobs;

use WPStaging\Backend\Modules\Jobs\JobExecutable;
use WPStaging\Core\Utils\Logger;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Utils\Strings;
use wpdb;

/**
 * @package WPStaging\Backend\Modules\Jobs
 */
class DatabaseTmpRename extends JobExecutable
{

    /**
     * @var wpdb
     */
    private $productionDb;

    /**
     * This contains an object of all existing database tables.
     * @var array
     */
    private $existingTables = [];

    /**
     * @var DatabaseTmp
     */
    private $databaseTmpJob;

    /**
     * Initialize
     */
    public function initialize()
    {
        $this->productionDb = WPStaging::getInstance()->get('wpdb');
        $this->databaseTmpJob = new DatabaseTmp();
        $this->getAllTables();

        $this->checkFatalError();
    }

    protected function checkFatalError()
    {
        if (DatabaseTmp::TMP_PREFIX === $this->productionDb->prefix) {
            $this->returnException('Fatal Error: Prefix ' . $this->productionDb->prefix . ' is used for the live site and used for the temporary database tables hence we can not replace the production database. Please ask support@wp-staging.com how to resolve this.');
        }
    }

    /**
     * Calculate Total Steps in This Job and Assign It to $this->options->totalSteps
     * @return void
     */
    protected function calculateTotalSteps()
    {
        $this->options->totalSteps = 1;
    }

    /**
     * Execute the Current Step
     * Returns false when over threshold limits are hit or when the job is done, true otherwise
     * @return bool
     * @throws \Exception
     */
    protected function execute()
    {
        // Over limits threshold
        if ($this->isOverThreshold()) {
            $this->log('DB Rename: Is over threshold. Continuing ...');
            // Prepare response and save current progress
            $this->prepareResponse(false, false);
            $this->saveOptions();
            return false;
        }

        // Increment step by 1
        $this->prepareResponse(false, true);

        // Rename all tables. This is not done in chunks to execute it as fast as possible and prevent interruption
        foreach ($this->options->tables as $table) {
            if ($this->renameTable($table) === false) {
                return true;
            }
        }

        $this->prepareResponse(true, false);

        $this->flush();
        $this->isFinished();
        return false;
    }

    /**
     * Flush wpdb cache and permalinks rewrite rules
     * to prevent 404s and other oddities
     * @global object $wp_rewrite
     */
    protected function flush()
    {
        wp_cache_flush();
        global $wp_rewrite;
        $wp_rewrite->init();
        flush_rewrite_rules(true); // true = hard refresh, recreates the .htaccess file
    }


    /**
     * Rename tmp tables to production ones
     * @param $tableName
     * @return bool true
     */
    protected function renameTable($tableName)
    {
        // Table name without prefix
        $tableNameWithoutPrefix = (new Strings())->str_replace_first($this->options->prefix, '', $tableName);

        if ($this->databaseTmpJob->shouldReplaceTablePrefix($tableName, $this->options->prefix)) {
            $srcTableName = DatabaseTmp::TMP_PREFIX . $tableNameWithoutPrefix;
            $destTableName = $this->productionDb->prefix . $tableNameWithoutPrefix;
        } else {
            $srcTableName = DatabaseTmp::TMP_PREFIX_PRESERVE . $tableNameWithoutPrefix;
            $destTableName = $tableName;
        }

        if ($this->tableExists($srcTableName)) {
            $this->log('DB Rename: ' . $srcTableName . ' to ' . $destTableName);
        }

        $this->productionDb->query('SET FOREIGN_KEY_CHECKS=0;');

        /**
         * Attention: Dropping table first and then renaming it works much more reliable than just using the RENAME statement
         */
        // Drop live table
        if ($this->productionDb->query("DROP TABLE IF EXISTS `$destTableName`") === false) {
            $this->log("DB Rename: Error - Can not drop table $destTableName Error: {$this->productionDb->last_error}", Logger::TYPE_ERROR);
            $this->returnException("DB Rename: Error - Can not drop table $destTableName db error - " . $this->productionDb->last_error);
        }

        // Rename tmp table to live table
        if ($this->productionDb->query("RENAME TABLE `$srcTableName` TO `$destTableName`") === false) {
            $this->log("DB Rename: Error - Can not rename table $srcTableName TO $destTableName Error: {$this->productionDb->last_error}", Logger::TYPE_ERROR);
            $this->returnException("DB Rename: Error - Can not rename table $srcTableName TO $destTableName db error - " . $this->productionDb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Drop table if necessary
     * @param string $table
     */
    protected function dropTable($table)
    {
        // Check if table already exists
        if ($this->tableExists($table) === false) {
            return;
        }

        $this->log("DB Rename: $table already exists, dropping it first");
        if ($this->productionDb->query("DROP TABLE `$table`") === false) {
            //$this->db->query("ROLLBACK");
            $this->log("DB Rename: Can not drop table $table");
            $this->returnException("DB Rename: Can not drop table $table");
        }
    }

    /**
     * Check if table needs to be dropped first
     * @param string $new
     * @param string $old
     * @return bool
     */
    protected function shouldDropTable($new, $old)
    {
        return ($old === $new);
    }

    /**
     * Check if table exists
     * @param string $table
     * @return boolean
     */
    protected function tableExists($table)
    {
        if (in_array($table, $this->existingTables, true)) {
            return true;
        }
        return false;
    }

    /**
     * Get all available tables
     */
    protected function getAllTables()
    {
        $sql = "SHOW TABLE STATUS";
        $tables = $this->productionDb->get_results($sql);
        foreach ($tables as $table) {
            $this->existingTables[] = $table->Name;
        }
    }


    /**
     * Push is finished
     * @return boolean
     */
    protected function isFinished()
    {

        // This job is finished
        if ($this->options->currentStep >= $this->options->totalSteps) {
            $this->log('DB Rename: Has been finished successfully. Cleaning up...');
            $this->prepareResponse(true, false);
            return true;
        }


        return false;
    }
}
