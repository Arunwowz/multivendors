<?php

namespace WPStaging\Pro\Backup\Service\Database\Exporter;

use Exception;
use WPStaging\Framework\Adapter\Database;
use WPStaging\Framework\Database\TableService;

class DDLExporter extends AbstractExporter
{
    protected $tableService;

    protected $viewDDLOrder;

    protected $tables = [];

    protected $views  = [];

    protected $excludedTables = [];

    protected $nonWpTables    = [];

    public function __construct(Database $database, TableService $tableService, ViewDDLOrder $viewDDLOrder)
    {
        parent::__construct($database);
        $this->tableService = $tableService;
        $this->viewDDLOrder = $viewDDLOrder;
    }

    public function exportDDLTablesAndViews()
    {
        $this->file->fwrite($this->getHeader());

        $this->client->query("SET SESSION sql_mode = ''");

        $this->tables = $this->tableService->findTableNamesStartWith($this->database->getPrefix());
        $this->views = $this->tableService->findViewsNamesStartWith($this->database->getPrefix());

                $tablesThenViews = array_merge($this->tables, $this->views);

        foreach ($tablesThenViews as $tableOrView) {
            if ($this->isView($tableOrView, $this->views)) {
                $this->viewDDLOrder->enqueueViewToBeWritten($tableOrView, $this->tableService->getCreateViewQuery($tableOrView));
                continue;
            }

            if (in_array($tableOrView, $this->excludedTables)) {
                continue;
            }

            $this->writeQueryCreateTable($tableOrView);
        }

        $this->writeQueryNonWpTables();

        foreach ($this->viewDDLOrder->tryGetOrderedViews() as $viewName => $query) {
            $this->writeQueryCreateViews($viewName, $query);
        }
    }

    public function getTables()
    {
        return $this->tables;
    }

    public function getNonWPTables()
    {
        return $this->nonWpTables;
    }

    protected function writeQueryNonWpTables()
    {
        $nonPrefixedTables = $this->getNonPrefixedTablesFromFilter();

        foreach ($nonPrefixedTables as $table) {
            if (in_array($table, $this->tables)) {
                continue;
            }

            if (in_array($table, $this->views)) {
                continue;
            }

            $this->addNonWpTable($table);
        }
    }

    protected function getNonPrefixedTablesFromFilter()
    {
        $nonPrefixedTables = apply_filters('wpstg.backup.tables.non-prefixed', []);

        return is_array($nonPrefixedTables) ? $nonPrefixedTables : [];
    }

    protected function addNonWpTable($table)
    {
        $isTableAdded = $this->writeQueryCreateTable($table, false);
        if ($isTableAdded !== false) {
            $this->nonWpTables[] = $table;
        }
    }

    protected function writeQueryCreateTable($tableName, $isWpTable = true)
    {
        $newTableName = $tableName;

        if ($isWpTable) {
            $newTableName = $this->getPrefixedTableName($tableName);
        }

        $createTableQuery = $this->tableService->getCreateTableQuery($tableName);

        if ($createTableQuery === '' && !$isWpTable) {
            return false;
        }

        $dropTable = "\nDROP TABLE IF EXISTS `{$newTableName}`;\n";
        $this->file->fwrite($dropTable);

        if ($isWpTable) {
            $createTableQuery = str_replace($tableName, $newTableName, $createTableQuery);
        }

        $createTableQuery = $this->replaceTableConstraints($createTableQuery);
        $createTableQuery = $this->replaceTableOptions($createTableQuery);
        $this->file->fwrite(preg_replace('#\s+#', ' ', $createTableQuery));

        $this->file->fwrite(";\n\n");

        return $newTableName;
    }

    private function replaceTableConstraints($input)
    {
        $pattern = [
            '/\s+CONSTRAINT(.+)REFERENCES(.+),/i',
            '/,\s+CONSTRAINT(.+)REFERENCES(.+)/i',
        ];

        return preg_replace($pattern, '', $input);
    }

    private function replaceTableOptions($input)
    {
        $search = [
            'TYPE=InnoDB',
            'TYPE=MyISAM',
            'ENGINE=Aria',
            'TRANSACTIONAL=0',
            'TRANSACTIONAL=1',
            'PAGE_CHECKSUM=0',
            'PAGE_CHECKSUM=1',
            'TABLE_CHECKSUM=0',
            'TABLE_CHECKSUM=1',
            'ROW_FORMAT=PAGE',
            'ROW_FORMAT=FIXED',
            'ROW_FORMAT=DYNAMIC',
        ];
        $replace = [
            'ENGINE=InnoDB',
            'ENGINE=MyISAM',
            'ENGINE=MyISAM',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];

        return str_ireplace($search, $replace, $input);
    }

    private function isView($tableName, array $views)
    {
        return in_array($tableName, $views, true);
    }

    protected function writeQueryCreateViews($tableName, $createViewQuery)
    {
        $prefixedTableName = $this->getPrefixedTableName($tableName);

        $dropView = "\nDROP VIEW IF EXISTS `{$prefixedTableName}`;\n";
        $this->file->fwrite($dropView);

        $createViewQuery = $this->replaceViewIdentifiers($createViewQuery);
        $createViewQuery = str_replace($tableName, $prefixedTableName, $createViewQuery);
        $createViewQuery = $this->replaceViewOptions($createViewQuery);
        $this->file->fwrite($createViewQuery);

        $this->file->fwrite(";\n\n");
    }

    private function replaceViewIdentifiers($sql)
    {
        foreach (array_merge($this->tables, $this->views) as $tableName) {
            $newTableName = $this->replacePrefix($tableName, '{WPSTG_TMP_PREFIX}');
            $sql = str_ireplace("`$tableName`", "`$newTableName`", $sql);
        }

        return $sql;
    }

    private function replaceViewOptions($input)
    {
        return preg_replace('/CREATE(.+?)VIEW/i', 'CREATE VIEW', $input);
    }

    protected function getHeader()
    {
        return sprintf(
            "-- WP Staging SQL Export Dump\n" .
            "-- https://wp-staging.com/\n" .
            "--\n" .
            "-- Host: %s\n" .
            "-- Database: %s\n" .
            "-- Class: %s\n" .
            "--\n",
            $this->getWpDb()->dbhost,
            $this->getWpDb()->dbname,
            get_class($this)
        );
    }
}
