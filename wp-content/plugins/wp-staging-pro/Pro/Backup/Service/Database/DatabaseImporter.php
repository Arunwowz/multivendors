<?php

namespace WPStaging\Pro\Backup\Service\Database;

use WPStaging\Framework\Queue\FinishedQueueException;
use WPStaging\Framework\Traits\ResourceTrait;
use WPStaging\Pro\Backup\Dto\Job\JobImportDataDto;
use WPStaging\Pro\Backup\Dto\JobDataDto;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Exceptions\ThresholdException;
use WPStaging\Pro\Backup\Service\Database\Exporter\RowsExporter;
use WPStaging\Pro\Backup\Service\Database\Importer\Insert\QueryInserter;
use WPStaging\Pro\Backup\Service\Database\Importer\QueryCompatibility;
use WPStaging\Vendor\Psr\Log\LoggerInterface;
use RuntimeException;
use WPStaging\Framework\Adapter\Database;
use WPStaging\Framework\Adapter\Database\InterfaceDatabaseClient;
use WPStaging\Framework\Filesystem\FileObject;
use WPStaging\Framework\Database\SearchReplace;
use WPStaging\Pro\Backup\Ajax\Import\PrepareImport;
use WPStaging\Pro\Backup\Task\ImportTask;

use function WPStaging\functions\debug_log;

class DatabaseImporter
{
    use ResourceTrait;

    private $file;

    private $totalLines;

    private $client;

    private $database;

    private $logger;

    private $stepsDto;

    private $searchReplace;

    private $searchReplaceForPrefix;

    private $wpdb;

    private $tmpDatabasePrefix;

    private $jobImportDataDto;

    private $queryInserter;

    private $smallerSearchLength;

    private $binaryFlagLength;

    private $queryCompatibility;

    private $importTask;

    private $getIsSameSiteBackupRestore = false;

    private $tablesExcludedFromSearchReplace = [];

    public function __construct(Database $database, JobDataDto $jobImportDataDto, QueryInserter $queryInserter, QueryCompatibility $queryCompatibility)
    {
        $this->client = $database->getClient();
        $this->wpdb = $database->getWpdba();
        $this->database = $database;
        $this->jobImportDataDto = $jobImportDataDto;

        $this->queryInserter = $queryInserter;
        $this->queryCompatibility = $queryCompatibility;

        $this->binaryFlagLength = strlen(RowsExporter::BINARY_FLAG);
    }

    public function setFile($filePath)
    {
        $this->file = new FileObject($filePath);
        $this->totalLines = $this->file->totalLines();

        return $this;
    }

    public function seekLine($line)
    {
        if (!$this->file) {
            throw new RuntimeException('Restore file is not set');
        }
        $this->file->seek($line);

        return $this;
    }

    public function import($tmpDatabasePrefix)
    {
        $this->tmpDatabasePrefix = $tmpDatabasePrefix;

        $this->setupSearchReplaceForPrefix();

        if (!$this->file) {
            throw new RuntimeException('Restore file is not set');
        }

        $this->exec("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");

        try {
            while (true) {
                try {
                    $this->execute();
                } catch (\OutOfBoundsException $e) {
                                        $this->logger->debug($e->getMessage());
                }
            }
        } catch (FinishedQueueException $e) {
            $this->stepsDto->finish();
        } catch (ThresholdException $e) {
        } catch (\Exception $e) {
            $this->stepsDto->setCurrent($this->file->key());
            $this->logger->critical(substr($e->getMessage(), 0, 1000));
        }

                $this->queryInserter->commit();

        $this->stepsDto->setCurrent($this->file->key());
    }

    protected function setupSearchReplaceForPrefix()
    {

        $this->searchReplaceForPrefix = new SearchReplace(['{WPSTG_TMP_PREFIX}', '{WPSTG_FINAL_PREFIX}'], [$this->tmpDatabasePrefix, $this->wpdb->getClient()->prefix], true, []);
    }

    public function setup(LoggerInterface $logger, StepsDto $stepsDto, ImportTask $task)
    {
        $this->logger = $logger;
        $this->stepsDto = $stepsDto;
        $this->importTask = $task;

        $this->getIsSameSiteBackupRestore = $this->jobImportDataDto->getIsSameSiteBackupRestore();
        $this->queryInserter->initialize($this->database, $this->jobImportDataDto, $logger);

        $this->tablesExcludedFromSearchReplace = $this->jobImportDataDto->getBackupMetadata()->getNonWpTables();

        return $this;
    }

    public function setSearchReplace(SearchReplace $searchReplace)
    {
        $this->searchReplace = $searchReplace;

                $this->smallerSearchLength = min($searchReplace->getSmallerSearchLength(), $this->binaryFlagLength);

        return $this;
    }

    public function getTotalLines()
    {
        return $this->totalLines;
    }

    private function execute()
    {
        if ($this->isDatabaseImportThreshold()) {
            throw new ThresholdException();
        }

        $query = $this->findExecutableQuery();

        if (!$query) {
            throw new FinishedQueueException();
        }

        $query = $this->searchReplaceForPrefix->replace($query);

        $query = $this->maybeShorterTableNameForDropTableQuery($query);
        $query = $this->maybeShorterTableNameForCreateTableQuery($query);

        $this->replaceTableCollations($query);

        if (strpos($query, 'INSERT INTO') === 0) {
            if ($this->isExludedInsertQuery($query)) {
                debug_log('processQuery - This query has been skipped from inserting by using a custom filter: ' . $query);
                $this->logger->warning(__(sprintf('The query has been skipped from inserting by using a custom filter: %s.', $query), 'wp-staging'));
                return false;
            }

            if (
                !$this->getIsSameSiteBackupRestore
                || (strpos($query, RowsExporter::BINARY_FLAG) !== false)
                || (strpos($query, RowsExporter::NULL_FLAG) !== false)
            ) {
                    $this->searchReplaceInsertQuery($query);
            }

            try {
                $result = $this->queryInserter->processQuery($query);
            } catch (\Exception $e) {
                        throw $e;
            }

            if ($result === null && $this->queryInserter->getLastError() !== false) {
                $this->logger->warning($this->queryInserter->getLastError());
            }
        } else {
                $this->queryInserter->commit();

            $this->queryCompatibility->removeDefiner($query);
            $this->queryCompatibility->removeSqlSecurity($query);
            $this->queryCompatibility->removeAlgorithm($query);

            $result = $this->exec($query);
        }

        if ($result === false) {
            switch ($this->client->errno()) {
                case 1030:
                    $this->queryCompatibility->replaceTableEngineIfUnsupported($query);
                    $result = $this->exec($query);

                    if ($result) {
                        $this->logger->warning(__('Engine changed to InnoDB, as it your MySQL server does not support MyISAM.', 'wp-staging'));
                    }

                    break;
                case 1071:
                case 1709:
                    $this->queryCompatibility->replaceTableRowFormat($query);
                    $result = $this->exec($query);

                    if ($result) {
                        $this->logger->warning(__('Row format changed to DYNAMIC, as it would exceed the maximum length according to your MySQL settings. To not see this message anymore, please upgrade your MySQL version or increase the row format.', 'wp-staging'));
                    }

                    break;
                case 1214:
                    $this->queryCompatibility->removeFullTextIndexes($query);
                    $result = $this->exec($query);

                    if ($result) {
                        $this->logger->warning(__('FULLTEXT removed from query, as your current MySQL version does not support it. To not see this message anymore, please upgrade your MySQL version.', 'wp-staging'));
                    }

                    break;
                case 1226:
                    if (stripos($this->client->error(), 'max_queries_per_hour') !== false) {
                        throw new RuntimeException(__(sprintf('Your server has reached the maximum allowed queries per hour set by your admin or hosting provider. Please increase MySQL max_queries_per_hour_limit. <a href="https://wp-staging.com/docs/mysql-database-error-codes/" target="_blank">Technical details</a>'), 'wp-staging'));
                    } elseif (stripos($this->client->error(), 'max_updates_per_hour') !== false) {
                        throw new RuntimeException(__(sprintf('Your server has reached the maximum allowed updates per hour set by your admin or hosting provider. Please increase MySQL max_updates_per_hour. <a href="https://wp-staging.com/docs/mysql-database-error-codes/" target="_blank">Technical details</a>'), 'wp-staging'));
                    } elseif (stripos($this->client->error(), 'max_connections_per_hour') !== false) {
                        throw new RuntimeException(__(sprintf('Your server has reached the maximum allowed connections per hour set by your admin or hosting provider. Please increase MySQL max_connections_per_hour. <a href="https://wp-staging.com/docs/mysql-database-error-codes/" target="_blank">Technical details</a>'), 'wp-staging'));
                    } elseif (stripos($this->client->error(), 'max_user_connections') !== false) {
                        throw new RuntimeException(__(sprintf('Your server has reached the maximum allowed connections per hour set by your admin or hosting provider. Please increase MySQL max_user_connections. <a href="https://wp-staging.com/docs/mysql-database-error-codes/" target="_blank">Technical details</a>'), 'wp-staging'));
                    }

                    break;
                case 1813:
                    throw new RuntimeException(__('Could not import the database. MySQL returned the error code 1813, which is related to a tablespace error that WP STAGING can\'t handle. Please contact your hosting company.', 'wp-staging'));
            }

            if (defined('WPSTG_DEBUG') && WPSTG_DEBUG) {
                $this->logger->warning(__(sprintf('Database Importer - Failed Query: %s', substr($query, 0, 1000)), 'wp-staging'));
                debug_log(__(sprintf('Database Importer Failed Query: %s', substr($query, 0, 1000)), 'wp-staging'));
            }

            $additionalInfo = '';
            $currentDbVersion = $this->database->getSqlVersion($compact = true);
            $backupDbVersion = $this->jobImportDataDto->getBackupMetadata()->getSqlServerVersion();
            if ($backupDbVersion !== $currentDbVersion) {
                $additionalInfo = sprintf(' Your current MySQL version is %s. If this issue persists, try using the same MySQL version used to create this Backup (%s).', $currentDbVersion, $backupDbVersion);
            }

            throw new RuntimeException(__(sprintf('Could not import query. MySQL has returned the error code %d, with message "%s".', $this->client->errno(), $this->client->error())) . $additionalInfo);
        }
    }

    protected function maybeShorterTableNameForDropTableQuery(&$query)
    {
        if (strpos($query, "DROP TABLE IF EXISTS") !== 0) {
            return $query;
        }

        preg_match('#^DROP TABLE IF EXISTS `(.+?(?=`))`;$#', $query, $dropTableExploded);

        $tableName = $dropTableExploded[1];
        if (strlen($tableName) > 64) {
            $tableName = $this->importTask->addShortNameTable($tableName, PrepareImport::TMP_DATABASE_PREFIX);
        }

        return "DROP TABLE IF EXISTS `$tableName`;";
    }

    protected function maybeShorterTableNameForCreateTableQuery(&$query)
    {
        if (strpos($query, "CREATE TABLE") !== 0) {
            return $query;
        }

        preg_match('#^CREATE TABLE `(.+?(?=`))`#', $query, $createTableExploded);

        $tableName = $createTableExploded[1];
        if (strlen($tableName) > 64) {
            $shortName = $this->importTask->getShortNameTable($tableName, PrepareImport::TMP_DATABASE_PREFIX);
            return str_replace($tableName, $shortName, $query);
        }

        return $query;
    }

    protected function searchReplaceInsertQuery(&$query)
    {
        if (!$this->searchReplace) {
            throw new RuntimeException('SearchReplace not set');
        }

                $querySize = strlen($query);
        if ($querySize > ini_get('pcre.backtrack_limit')) {
            $this->logger->warning(
                sprintf(
                    __('Skipped search & replace on query: "%s" Increasing pcre.backtrack_limit can fix it! Query Size: %s. pcre.backtrack_limit: %s', 'wp-staging'),
                    substr($query, 0, 1000) . '...',
                    $querySize,
                    ini_get('pcre.backtrack_limit')
                )
            );
            return;
        }

        preg_match('#^INSERT INTO `(.+?(?=`))` VALUES (\(.+\));$#', $query, $insertIntoExploded);

        if (count($insertIntoExploded) !== 3) {
            debug_log($query);
            throw new \OutOfBoundsException('Skipping insert query. The query was logged....');
        }

        $tableName = $insertIntoExploded[1];
        if (strlen($tableName) > 64) {
            $tableName = $this->importTask->getShortNameTable($tableName, PrepareImport::TMP_DATABASE_PREFIX);
        }

        $values = $insertIntoExploded[2];

        preg_match_all("#'(?:[^'\\\]++|\\\.)*+'#s", $values, $valueMatches);

        if (count($valueMatches) !== 1) {
            throw new RuntimeException('Value match in query does not match.');
        }

        $valueMatches = $valueMatches[0];

        $query = "INSERT INTO `$tableName` VALUES (";

        foreach ($valueMatches as $value) {
            if (empty($value) || $value === "''") {
                $query .= "'', ";
                continue;
            }

            if ($value === "'" . RowsExporter::NULL_FLAG . "'") {
                $query .= "NULL, ";
                continue;
            }

            if (strlen($value) - 2 < $this->smallerSearchLength) {
                $query .= "{$value}, ";
                continue;
            }

            $value = substr($value, 1, -1);

            if (strpos($value, RowsExporter::BINARY_FLAG) === 0) {
                $query .= "UNHEX('" . substr($value, strlen(RowsExporter::BINARY_FLAG)) . "'), ";
                continue;
            }

            if ($this->getIsSameSiteBackupRestore || !$this->shouldSearchReplace($query)) {
                $query .= "'{$value}', ";
                continue;
            }

            if (is_serialized($value)) {
                $value = $this->undoMySqlRealEscape($value);
                $value = $this->searchReplace->replaceExtended($value);
                $value = $this->mySqlRealEscape($value);
            } else {
                $value = $this->searchReplace->replaceExtended($value);
            }

            $query .= "'{$value}', ";
        }

        $query = rtrim($query, ', ');

        $query .= ');';
    }

    protected function undoMySqlRealEscape(&$query)
    {
        $replacementMap = [
            "\\0" => "\0",
            "\\n" => "\n",
            "\\r" => "\r",
            "\\t" => "\t",
            "\\Z" => chr(26),
            "\\b" => chr(8),
            '\"' => '"',
            "\'" => "'",
            '\\\\' => '\\',
        ];

        return strtr($query, $replacementMap);
    }

    protected function mySqlRealEscape(&$query)
    {
        $replacementMap = [
            "\0" => "\\0",
            "\n" => "\\n",
            "\r" => "\\r",
            "\t" => "\\t",
            chr(26) => "\\Z",
            chr(8) => "\\b",
            '"' => '\"',
            "'" => "\'",
            '\\' => '\\\\',
        ];

        return strtr($query, $replacementMap);
    }

    protected function shouldSearchReplace($query)
    {
        if (empty($this->tablesExcludedFromSearchReplace)) {
            return true;
        }

        preg_match('#^INSERT INTO `(.+?(?=`))` VALUES#', $query, $insertIntoExploded);
        $tableName = $insertIntoExploded[0];

        return !in_array($tableName, $this->tablesExcludedFromSearchReplace);
    }

    private function findExecutableQuery()
    {
        while (!$this->file->eof()) {
            $line = $this->getLine();
            if ($this->isExecutableQuery($line)) {
                return $line;
            }
        }

        return null;
    }

    private function getLine()
    {
        if ($this->file->eof()) {
            return null;
        }

        return trim($this->file->readAndMoveNext());
    }

    private function isExecutableQuery($query = null)
    {
        if (!$query) {
            return false;
        }

                $first2Chars = substr($query, 0, 2);
        if ($first2Chars === '--' || strpos($query, '#') === 0) {
            return false;
        }

        if ($first2Chars === '/*') {
            return false;
        }

        if (stripos($query, 'start transaction;') === 0) {
            return false;
        }

        if (stripos($query, 'commit;') === 0) {
            return false;
        }

        if (substr($query, -strlen(1)) !== ';') {
            $this->logger->debug('Skipping query because it does not end with a semi-colon... The query was logged.');
            debug_log($query);

            return false;
        }

        return true;
    }

    private function exec($query)
    {
        $result = $this->client->query($query, true);

        return $result !== false;
    }

    private function replaceTableCollations(&$input)
    {
        static $search = [];
        static $replace = [];

        if (empty($search) || empty($replace)) {
            if (!$this->wpdb->getClient()->has_cap('utf8mb4_520')) {
                if (!$this->wpdb->getClient()->has_cap('utf8mb4')) {
                    $search = ['utf8mb4_0900_ai_ci', 'utf8mb4_unicode_520_ci', 'utf8mb4'];
                    $replace = ['utf8_unicode_ci', 'utf8_unicode_ci', 'utf8'];
                } else {
                    $search = ['utf8mb4_0900_ai_ci', 'utf8mb4_unicode_520_ci'];
                    $replace = ['utf8mb4_unicode_ci', 'utf8mb4_unicode_ci'];
                }
            } else {
                $search = ['utf8mb4_0900_ai_ci'];
                $replace = ['utf8mb4_unicode_520_ci'];
            }
        }

        $input = str_replace($search, $replace, $input);
    }

    private function isExludedInsertQuery($query)
    {
        $excludedQueries = apply_filters('wpstg.database.import.excludedQueries', []);

        if (empty($excludedQueries)) {
            return false;
        }

        foreach ($excludedQueries as $excludedQuery) {
            if (strpos($query, $excludedQuery) === 0) {
                return true;
            }
        }

        return false;
    }
}
