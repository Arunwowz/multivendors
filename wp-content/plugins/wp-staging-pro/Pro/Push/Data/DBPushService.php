<?php

namespace WPStaging\Pro\Push\Data;

use wpdb;
use WPStaging\Core\Utils\Logger;
use WPStaging\Framework\CloningProcess\Data\CloningService;
use WPStaging\Framework\CloningProcess\Data\DataCloningDto;

abstract class DBPushService extends CloningService
{
    /**
     * @var string
     */
    protected $tmpPrefix = 'wpstgtmp_';

    protected $stagingMultisiteBlogs = null;

    /**
     * Production Site DB
     *
     * @var wpdb
     */
    protected $productionDb;

    /**
     * Staging Site DB Base Prefix
     *
     * @var string
     */
    protected $stagingPrefix;

    public function __construct(DataCloningDto $dto)
    {
        parent::__construct($dto);

        $this->productionDb = $this->dto->getProductionDb();
        $this->stagingPrefix = $this->dto->getJob()->getOptions()->prefix;
    }

    /**
     * Check if table exists
     * @param string $table
     * @return boolean
     */
    protected function isTable($table)
    {
        if ($table != $this->dto->getProductionDb()->get_var("SHOW TABLES LIKE '{$table}'")) {
            $this->log("Table {$table} does not exist", Logger::TYPE_INFO);
            return false;
        }

        return true;
    }

    /**
     * Check if table is excluded
     * @param string $table
     * @return boolean
     */
    protected function isTableExcluded($table)
    {
        /**
         * Check first against the excludedTables
         * This is still required because of pre existing filters and hooks
         * @see WPStaging\Framework\Database\ExcludedTables
         */
        if (in_array($table, $this->dto->getJob()->getOptions()->excludedTables)) {
            return true;
        }

        // If table exists in tablePushSelection, that mean they are not excluded
        if (in_array($table, $this->dto->getJob()->getOptions()->tablePushSelection)) {
            return false;
        }

        return true;
    }

    /**
     * Check if table exists
     * @param string $table
     * @return boolean
     */
    protected function tableExists($table)
    {
        if ($table !== $this->dto->getProductionDb()->get_var("SHOW TABLES LIKE '{$table}'")) {
            $this->log("Table {$table} does not exist.");
            return false;
        }

        return true;
    }

    /**
     * Execute sql batch queries
     * @param mixed string|array
     */
    protected function executeSql($sqlbatch)
    {
        $queries = array_filter(explode(";\n", $sqlbatch));

        foreach ($queries as $query) {
            if ($this->dto->getProductionDb()->query($query) === false) {
                $this->log("DB Data Warning:  Can not execute query $query", Logger::TYPE_WARNING);
            }
        }

        return true;
    }

    /**
     * Mimics the mysql_real_escape_string function. Adapted from a post by 'feedr' on php.net.
     * @link   http://php.net/manual/en/function.mysql-real-escape-string.php#101248
     * @access public
     * @param  mixed string|array $input The string to escape.
     * @return array|string
     */
    protected function mysqlRealEscapeString($input)
    {
        if (is_array($input)) {
            return array_map(__METHOD__, $input);
        }

        if (!empty($input) && is_string($input)) {
            return str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $input);
        }

        return $input;
    }

    /**
     * Get Tmp Table Prefix
     *
     * @return string
     */
    protected function getTmpPrefix()
    {
        return $this->tmpPrefix;
    }

    /**
     * Get List of all Staging Site Blogs
     *
     * @return array[\WP_Site]
     */
    protected function getStagingMultisiteBlogs()
    {
        if (!$this->dto->isMultisite()) {
            return [];
        }

        if ($this->stagingMultisiteBlogs === null) {
            $db = $this->dto->getStagingDb();
            $prefix = $this->dto->getJob()->getOptions()->prefix;
            $this->stagingMultisiteBlogs = $db->get_results("SELECT * FROM {$prefix}blogs");
        }

        return $this->stagingMultisiteBlogs;
    }

    /**
     * Show Exception Message
     *
     * @param string $message
     */
    protected function returnException($message)
    {
        $this->dto->getJob()->returnException("DB Data Step {$this->dto->getStepNumber()}: " . $message);
    }
}
