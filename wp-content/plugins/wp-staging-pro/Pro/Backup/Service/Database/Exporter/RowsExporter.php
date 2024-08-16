<?php

namespace WPStaging\Pro\Backup\Service\Database\Exporter;

use Exception;
use WPStaging\Framework\Adapter\Database;
use WPStaging\Framework\Database\SearchReplace;
use WPStaging\Framework\Traits\DatabaseSearchReplaceTrait;
use WPStaging\Framework\Traits\MySQLRowsGeneratorTrait;
use WPStaging\Pro\Backup\Dto\Job\JobExportDataDto;
use WPStaging\Pro\Backup\Dto\JobDataDto;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

use function WPStaging\functions\debug_log;

class RowsExporter extends AbstractExporter
{
    use MySQLRowsGeneratorTrait;
    use DatabaseSearchReplaceTrait;

    protected $tableIndex = 0;

    protected $tableRowsOffset = 0;

    protected $totalRowsExported;

    protected $totalRowsInCurrentTable;

    protected $tables = [];

    protected $prefixedValues = [];

    protected $queriesToInsert = '';

    protected $jobDataDto;

    protected $logger;

    protected $writeToFileCount = 0;

    protected $searchReplaceForPrefix;

    protected $isRetryingAfterRepair = false;

    protected $tableName;

    protected $databaseName;

    protected $maxSplitSize;

    protected $isBackupSplit = false;

    protected $exceedSplitSize = false;

    protected $unInsertedSqlSize = 0;

    protected $specialFields;

    protected $nonWpTables;


    const NULL_FLAG = "{WPSTG_NULL}";


    const BINARY_FLAG = "{WPSTG_BINARY}";

    public function __construct(Database $database, JobDataDto $jobDataDto)
    {
        parent::__construct($database);

        $this->jobDataDto = $jobDataDto;

        $this->specialFields = ['user_roles', 'capabilities', 'user_level', 'dashboard_quick_press_last_post_id', 'user-settings', 'user-settings-time'];

        $this->prefixedValues = array_flip(array_map(function ($unprefixedValue) {
            return $this->database->getPrefix() . $unprefixedValue;
        }, $this->specialFields));

        $this->databaseName = $this->database->getWpdba()->getClient()->__get('dbname');
    }

    public function getTableRowsOffset()
    {
        return (int)$this->tableRowsOffset;
    }

    public function setTableRowsOffset($tableRowsOffset)
    {
        $this->tableRowsOffset = $tableRowsOffset;
    }

    public function getTableIndex()
    {
        return (int)$this->tableIndex;
    }

    public function isTableExcluded()
    {
        return in_array($this->getTableBeingExported(), $this->excludedTables);
    }

    public function setTableIndex($tableIndex)
    {
        $this->tableIndex = $tableIndex;

        if (!array_key_exists($this->tableIndex, $this->tables)) {
            throw new \RuntimeException('Table not found.');
        }

        $this->tableName = $this->tables[$this->tableIndex];
    }

    public function setTotalRowsExported($totalRowsExported)
    {
        $this->totalRowsExported = $totalRowsExported;
    }

    public function setTotalRowsInCurrentTable($totalRows)
    {
        $this->totalRowsInCurrentTable = $totalRows;
    }

    public function getTotalRowsExported()
    {
        return (int)$this->totalRowsExported;
    }

    public function getTableBeingExported()
    {
        return array_key_exists($this->tableIndex, $this->tables) ? $this->tables[$this->tableIndex] : '';
    }

    public function setIsBackupSplit($isBackupSplit)
    {
        $this->isBackupSplit = $isBackupSplit;
    }

    public function setMaxSplitSize($maxSplitSize)
    {
        $this->maxSplitSize = $maxSplitSize;
    }

    public function doExceedSplitSize()
    {
        return $this->exceedSplitSize;
    }

    public function countTotalRows()
    {
        if (!array_key_exists($this->tableIndex, $this->tables)) {
            throw new \RuntimeException('Table not found.');
        }

        $result = $this->client->query("SELECT COUNT(*) as `totalRows` FROM `$this->tableName`");

        if (!$result) {
            if ($this->isRetryingAfterRepair) {
                $this->throwUnableToCountException();
            }

            switch ($this->client->errno()) {
                case 144:
                case 145:
                    if ($this->client->query("REPAIR TABLE `$this->tableName`;")) {
                            $this->logger->warning(sprintf("Table %s is marked as crashed, we automatically repaired it.", $this->tableName));
                    } else {
                        $this->logger->warning(sprintf("Table %s is marked as crashed, we automatically repaired it but failed.", $this->tableName));
                    }

                    $this->isRetryingAfterRepair = true;

                    return $this->countTotalRows();
                    break;
                default:
                    $this->throwUnableToCountException();
            }
        }

        return (int)$result->fetch_object()->totalRows;
    }

    public function setupPrefixedValuesForSubsites()
    {
        if (!is_multisite()) {
            return;
        }

        $basePrefix = $this->database->getBasePrefix();

        foreach ($this->subsites as $subsite) {
            $siteId = $subsite['blog_id'];
            if (empty($siteId) || $siteId === 1) {
                continue;
            }

            $prefix = $basePrefix . $siteId . "_";

            $prefixedValues = array_flip(array_map(function ($unprefixedValue) use ($prefix) {
                return $prefix . $unprefixedValue;
            }, $this->specialFields));

            $this->prefixedValues = array_merge($this->prefixedValues, $prefixedValues);
        }
    }

    protected function setupExportSearchReplace()
    {
        global $wpdb;
        $this->searchReplaceForPrefix = new SearchReplace([$wpdb->base_prefix], ['{WPSTG_FINAL_PREFIX}'], true, []);
    }

    public function export($jobId, LoggerInterface $logger)
    {
        $this->client->query("SET SESSION sql_mode = ''");

        $this->logger = $logger;

        $prefixedTableName = $this->tableName;
        if (!in_array($this->tableName, $this->nonWpTables)) {
            $prefixedTableName = $this->getPrefixedTableName($this->tableName);
        }

        $tableColumns = $this->getColumnTypes($this->tableName);

        try {
            $numericPrimaryKey = $this->getPrimaryKey();
        } catch (Exception $e) {
            $numericPrimaryKey = null;
            if ($this->jobDataDto->getTableRowsOffset() === 0 && $this->jobDataDto->getTotalRowsOfTableBeingExported() > 300000) {
                $logger->info("The table $this->tableName does not have a compatible primary key, so it will get slower the more rows it exports...");
            }
        }

        $this->setupExportSearchReplace();

        do {
            $data = $this->rowsGenerator($this->databaseName, $this->tableName, $numericPrimaryKey, $this->tableRowsOffset, "rowsExporter_$jobId", $this->client, $this->jobDataDto);

            foreach ($data as $row) {
                $this->writeQueryInsert($row, $prefixedTableName, $tableColumns);
                if ($this->exceedSplitSize) {
                    if (!empty($this->queriesToInsert)) {
                        $this->file->fwrite($this->queriesToInsert);
                        $this->queriesToInsert = '';
                        $this->writeToFileCount = 0;
                        $this->unInsertedSqlSize = 0;
                    }

                    return max($this->totalRowsInCurrentTable - $this->totalRowsExported, 0);
                }

                $this->writeToFileCount++;
                $this->totalRowsExported++;

                if (!empty($numericPrimaryKey)) {
                    $this->tableRowsOffset = $row[$numericPrimaryKey];
                } else {
                    $this->tableRowsOffset++;
                }

                if ($this->writeToFileCount >= 10) {
                    $this->file->fwrite($this->queriesToInsert);
                    $this->queriesToInsert = '';
                    $this->writeToFileCount = 0;
                }
            }

            $rowsLeftToProcess = max($this->totalRowsInCurrentTable - $this->totalRowsExported, 0);
        } while (!$this->isThreshold() && $rowsLeftToProcess > 0);

        if (!empty($this->queriesToInsert)) {
            $this->file->fwrite($this->queriesToInsert);
            $this->queriesToInsert = '';
        }

        return $rowsLeftToProcess;
    }

    public function lockTable()
    {
        if (!$result = $this->client->query("LOCK TABLES `$this->tableName` WRITE;")) {
            \WPStaging\functions\debug_log("WP STAGING: Could not lock tables for exporting $this->tableName");
        }

        return $result;
    }

    public function unLockTables()
    {
        if (!$result = $this->client->query("UNLOCK TABLES;")) {
            \WPStaging\functions\debug_log("WP STAGING: Could not unlock tables after locking tables $this->tableName");
        }

        return $result;
    }

    public function setTables(array $tables = [])
    {
        $this->tables = $tables;
    }

    public function setNonWpTables($nonWpTables = [])
    {
        $this->nonWpTables = $nonWpTables;
    }

    private function getColumnTypes($tableName)
    {
        $column_types = [];

        $result = $this->client->query("SHOW COLUMNS FROM `{$tableName}`");
        while ($row = $this->client->fetchAssoc($result)) {
            if (isset($row['Field'])) {
                $column_types[strtolower($row['Field'])] = strtolower($row['Type']);
            }
        }

        $this->client->freeResult($result);

        return $column_types;
    }

    public function getPrimaryKey()
    {
        $query = "SELECT COLUMN_NAME 
                  FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_NAME = '$this->tableName'
                  AND TABLE_SCHEMA = '$this->databaseName'
                  AND IS_NULLABLE = 'NO'
                  AND DATA_TYPE IN ('int', 'bigint', 'smallint', 'mediumint')
                  AND COLUMN_KEY = 'PRI'
                  AND EXTRA like '%auto_increment%';";

        $result = $this->client->query($query);

        $primaryKey = $this->client->fetchObject($result);

        $this->client->freeResult($result);

        if (!is_object($primaryKey)) {
            throw new \UnexpectedValueException();
        }

        if (!property_exists($primaryKey, 'COLUMN_NAME')) {
            throw new \UnexpectedValueException();
        }

        if (empty($primaryKey->COLUMN_NAME)) {
            throw new \UnexpectedValueException();
        }

        return $primaryKey->COLUMN_NAME;
    }

    private function isTableCorrupt()
    {
        return $this->client->errno() === 1194;
    }

    protected function writeQueryInsert($row, $prefixedTableName, $tableColumns)
    {
        try {
            foreach ($row as $column => &$value) {
                if (is_null($value)) {
                    $nullFlag = self::NULL_FLAG;
                    $value = "'$nullFlag'";
                    continue;
                }

                if (
                                    strpos($tableColumns[strtolower($column)], 'binary') !== false ||
                                    strpos($tableColumns[strtolower($column)], 'blob') !== false
                ) {
                    $value = bin2hex($value);
                    $binaryFlag = static::BINARY_FLAG;
                    $value = "'$binaryFlag$value'";
                    continue;
                }

                if ($prefixedTableName === '{WPSTG_TMP_PREFIX}options' && $column === 'option_name') {
                    if (substr($value, 0, 1) === '_') {
                        foreach (['_transient_', '_site_transient_', '_wc_session_'] as $excludedOption) {
                            if (strpos($value, $excludedOption) === 0) {
                                throw new \OutOfBoundsException();
                            }
                        }
                    }

                    if (substr($value, 0, 22) === 'wpstg_analytics_event_') {
                        throw new \OutOfBoundsException();
                    }

                    if (isset($this->prefixedValues[$value])) {
                        $value = $this->searchReplaceForPrefix->replace($value);
                    }
                }

                if ($prefixedTableName === '{WPSTG_TMP_PREFIX}usermeta' && $column === 'meta_key') {
                    if (isset($this->prefixedValues[$value])) {
                        $value = $this->searchReplaceForPrefix->replace($value);
                    }
                }

                $value = "'{$this->client->escape($value)}'";
            }

            $this->exceedSplitSize = false;
            $insertQuery = "INSERT INTO `{$prefixedTableName}` VALUES (" . implode(',', $row) . ");\n";
            if (!$this->isBackupSplit) {
                $this->queriesToInsert .= $insertQuery;
                return;
            }

            $this->unInsertedSqlSize += strlen($insertQuery);

            if (($this->unInsertedSqlSize + $this->file->getSize()) > $this->maxSplitSize) {
                $this->exceedSplitSize = true;
                return;
            }

            $this->queriesToInsert .= $insertQuery;
        } catch (Exception $e) {
        }
    }

    protected function throwUnableToCountException()
    {
        throw new \RuntimeException(sprintf(
            'We could not count the rows of a given table. Table: %s MySQL Erro No: %s MySQL Error: %s',
            $this->tableName,
            $this->client->errno(),
            $this->client->error()
        ));
    }
}
