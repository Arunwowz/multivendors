<?php

namespace WPStaging\Backend\Pro\Modules\Jobs;

use WPStaging\Backend\Modules\Jobs\CloningProcess;
use WPStaging\Framework\Database\QueryBuilder\SelectQuery;
use WPStaging\Framework\Utils\Strings;

/**
 * Class Database
 * @package WPStaging\Backend\Modules\Jobs
 */
class DatabaseTmp extends CloningProcess
{
    /** Total number of tables */
    private $total = 0;

    /** This is the default temporary prefix for all tables that contains the wp core prefix in the production site e.g. wp_users => wpstgtmp_users */
    const TMP_PREFIX = 'wpstgtmp_';

    /** This is the temporary prefix for all tables that do not contain a custom prefix or
     * none prefix at all on the production site e.g my_custom_table => wpstgtmx_mycustom_table
     *
     * We use this special prefix to tell WP STAGING to not change the prefix for a particular table on pushing.
     */
    const TMP_PREFIX_PRESERVE = 'wpstgtmx_';

    /** @var Strings */
    private $strings;

    /**
     * @var SelectQuery
     */
    protected $selectQueryBuilder;

    public function initialize()
    {
        $this->total = count($this->options->tables);
        $this->strings = new Strings();
        $this->selectQueryBuilder = new SelectQuery();
        $this->initializeDbObjects();
    }


    /**
     * Calculate Total Steps in This Job and Assign It to $this->options->totalSteps
     * @return void
     */
    protected function calculateTotalSteps()
    {
        $this->options->totalSteps = $this->total !== 0 ? $this->total : 1;
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
            // Prepare response and save current progress
            $this->prepareResponse(false, false);
            $this->saveOptions();
            return false;
        }

        // No table selected, finished
        if ($this->total === 0) {
            $this->prepareResponse(true, false);
            return false;
        }

        // No more steps, finished
        if ($this->options->currentStep > $this->total || !isset($this->options->tables[$this->options->currentStep])) {
            $this->prepareResponse(true, false);
            return false;
        }

        if (in_array($this->options->tables[$this->options->currentStep], $this->options->excludedTables)) {
            $this->prepareResponse();
            return true;
        }

        // Copy table
        if (($this->isExternalDatabase() || !$this->stopExecution()) && !$this->copyTable($this->options->tables[$this->options->currentStep])) {
            $this->prepareResponse(false, false);

            // Not finished
            return true;
        }

        // Prepare Response
        $this->prepareResponse();

        // Not finished
        return true;
    }


    /**
     * Stop Execution immediately
     * return mixed bool | json
     */
    private function stopExecution()
    {
        if ($this->productionDb->prefix == self::TMP_PREFIX) {
            $this->returnException('Fatal Error 9: Prefix ' . $this->productionDb->prefix . ' is used for the live site hence it can not be used for the staging site as well. Please ask support@wp-staging.com how to resolve this.');
        }
        return false;
    }

    /**
     * @param $srcTableName
     * @param $prefix
     * @return bool
     */
    public function shouldReplaceTablePrefix($srcTableName, $prefix)
    {
        return $this->strings->startsWith($srcTableName, $prefix);
    }

    /**
     * Copy Tables
     * @param string $srcTableName
     * @return bool
     */
    private function copyTable($srcTableName)
    {
        if ($this->shouldReplaceTablePrefix($srcTableName, $this->options->prefix)) {
            $destTableName = self::TMP_PREFIX . $this->strings->str_replace_first($this->options->prefix, null, $srcTableName);
        } else {
            $destTableName = self::TMP_PREFIX_PRESERVE . $srcTableName;
        }

        // Drop table if necessary
        $this->dropTable($destTableName);

        // Save current job
        $this->setJob($destTableName);

        // Beginning of the job
        if (!$this->startJob($destTableName, $srcTableName)) {
            return true;
        }

        // Copy data
        $this->copyData($srcTableName, $destTableName);

        // Finish the step
        return $this->finishStep();
    }


    /**
     * Copy data from old table to new table
     * @param string $destTableName
     * @param string $srcTableName
     */
    private function copyData($srcTableName, $destTableName)
    {
        if ($this->isExternalDatabase()) {
            $this->copyDataExternal($destTableName, $srcTableName);
            return;
        }

        $rows = $this->options->job->start + $this->settings->queryLimit;
        $this->log(
            "DB tmp table: $srcTableName as $destTableName from {$this->options->job->start} to $rows records"
        );

        $preparedQuery = $this->selectQueryBuilder->prepareQueryWithFilter($srcTableName, (int)$this->settings->queryLimit, $this->options->job->start, 'pushing');
        $preparedValues = $this->selectQueryBuilder->getPreparedValues();
        $preparedQuery = "INSERT INTO `$destTableName` $preparedQuery";

        if (count($preparedValues) > 0) {
            $preparedQuery = $this->productionDb->prepare($preparedQuery, $preparedValues);
        }

        $this->productionDb->query("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
        $this->productionDb->query($preparedQuery);

        // Set new offset
        $this->options->job->start += $this->settings->queryLimit;
    }

    /**
     * Copy data from old table to new table
     * @param string $destTableName
     * @param string $srcTableName
     */
    private function copyDataExternal($destTableName, $srcTableName)
    {
        $rows = $this->options->job->start + $this->settings->queryLimit;

        $this->log(
            "DB tmp table: INSERT {$this->stagingDb->dbname}.$srcTableName as {$this->productionDb->dbname}.$destTableName from {$this->options->job->start} to $rows records"
        );

        $preparedQuery = $this->selectQueryBuilder->prepareQueryWithFilter($srcTableName, (int)$this->settings->queryLimit, $this->options->job->start, 'pushing');
        $preparedValues = $this->selectQueryBuilder->getPreparedValues();

        if (count($preparedValues) > 0) {
            $preparedQuery = $this->stagingDb->prepare($preparedQuery, $preparedValues);
        }

        // Get data from staging site
        $rows = $this->stagingDb->get_results($preparedQuery, ARRAY_A);

        // Start transaction
        $this->productionDb->query('SET autocommit=0;');
        $this->productionDb->query('SET FOREIGN_KEY_CHECKS=0;');
        $this->productionDb->query('START TRANSACTION;');

        // Copy into production site
        foreach ($rows as $row) {
            $escaped_values = wpstg_mysql_escape_mimic(array_values($row));
            $values = implode("', '", $escaped_values);
            $this->productionDb->query("INSERT INTO `$destTableName` VALUES ('$values')");
        }

        // Commit transaction
        $this->productionDb->query('COMMIT;');
        $this->productionDb->query('SET autocommit=1;');

        // Set new offset
        $this->options->job->start += $this->settings->queryLimit;
    }

    /**
     * Set the job
     * @param string $tableName
     */
    private function setJob($tableName)
    {
        if (isset($this->options->job->current)) {
            return;
        }

        $this->options->job->current = $tableName;
        $this->options->job->start = 0;
    }

    /**
     * Start Job
     * @param string $destTableName
     * @param string $srcTableName
     * @return bool
     */
    private function startJob($destTableName, $srcTableName)
    {
        if ($this->isExternalDatabase()) {
            return $this->startJobExternal($destTableName, $srcTableName);
        }

        if ($this->options->job->start != 0) {
            return true;
        }

        $this->log("DB tmp table: CREATE table $destTableName");

        $this->productionDb->query("CREATE TABLE `$destTableName` LIKE `$srcTableName`");

        $this->options->job->total = (int)$this->productionDb->get_var("SELECT COUNT(1) FROM `$srcTableName`");

        if ($this->options->job->total == 0) {
            $this->finishStep();
            return false;
        }

        return true;
    }

    /**
     * Start Job and create tmp table
     * @param string $destTableName
     * @param string $srcTableName
     * @return bool
     */
    private function startJobExternal($destTableName, $srcTableName)
    {
        if ($this->options->job->start != 0) {
            return true;
        }

        $this->log("DB tmp table: CREATE table `{$this->productionDb->dbname}.$destTableName`");

        // Build CREATE statement for table from staging db
        $sql = $this->getCreateStatement($srcTableName);

        // Search & replace to prefixed tmp table wpstgtmp_*
        $sql = str_replace("CREATE TABLE `$srcTableName`", "CREATE TABLE `$destTableName`", $sql);

        $sql = wpstg_unique_constraint($sql);

        $this->productionDb->query('SET FOREIGN_KEY_CHECKS=0;');

        // Execute Query
        if ($this->productionDb->query($this->adaptCreateStatement($sql)) === false) {
            $this->returnException("DB Tmp Table: Error - Can not copy table $srcTableName TO $destTableName Query: $sql db error - " . $this->productionDb->last_error);
        }

        // Count rows
        $this->options->job->total = (int)$this->stagingDb->get_var("SELECT COUNT(1) FROM `{$this->stagingDb->dbname}`.`$srcTableName`");

        if ($this->options->job->total == 0) {
            $this->finishStep();
            return false;
        }

        return true;
    }

    /**
     * Change create statements according to MySQL version
     * @param string $sqlQuery
     * @return string
     */
    private function adaptCreateStatement($sqlQuery)
    {
        $fromDbVersion = $this->stagingDb->get_var("SELECT VERSION()");

        $toDbVersion = $this->productionDb->get_var("SELECT VERSION()");

        // If same version, all is good
        if (version_compare($fromDbVersion, $toDbVersion) == 0) {
            return $sqlQuery;
        }

        // Change from unicode 5.2 (520) to "normal" utf8mb4 unicode on MySQL versions before 5.6
        if (version_compare($toDbVersion, '5.6', '<')) {
            $sqlQuery = str_replace('utf8mb4_unicode_520_ci', 'utf8mb4_unicode_ci', $sqlQuery);
            $sqlQuery = str_replace('utf8_unicode_520_ci', 'utf8_unicode_ci', $sqlQuery);
        }

        return $sqlQuery;
    }

    /**
     * Get MySQL query create table
     *
     * @param string
     * @return array
     */
    private function getCreateStatement($tableName)
    {
        $row = $this->stagingDb->get_results("SHOW CREATE TABLE `$tableName`", ARRAY_A);

        // Get CREATE statement
        if (isset($row[0]['Create Table'])) {
            return $row[0]['Create Table'];
        }
        return [];
    }

    /**
     * Finish the step
     */
    private function finishStep()
    {
        // This job is not finished yet
        if ($this->options->job->total > $this->options->job->start) {
            return false;
        }

        // Add it to cloned tables listing
        $this->options->clonedTables[] = $this->options->tables[$this->options->currentStep];

        // Reset job
        $this->options->job = new \stdClass();

        return true;
    }

    /**
     * Drop table if necessary
     * @param string $destTableName
     */
    private function dropTable($destTableName)
    {
        $srcTableName = $this->productionDb->get_var($this->productionDb->prepare("SHOW TABLES LIKE %s", $destTableName));

        if (!$this->shouldDropTable($destTableName, $srcTableName)) {
            return;
        }

        $this->log("DB tmp table: $destTableName already exists, dropping it first");
        $this->productionDb->query("DROP TABLE `$destTableName`");
    }

    /**
     * Check if table needs to be dropped
     * @param string $destTableName
     * @param string $srcTableName
     * @return bool
     */
    private function shouldDropTable($destTableName, $srcTableName)
    {
        return (
            $srcTableName == $destTableName &&
            (
                !isset($this->options->job->current) ||
                !isset($this->options->job->start) ||
                $this->options->job->start == 0
            )
        );
    }
}
