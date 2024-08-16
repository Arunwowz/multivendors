<?php

namespace WPStaging\Pro\Backup\Service\Database\Exporter;

use WPStaging\Framework\Adapter\Database;
use WPStaging\Framework\Adapter\Database\InterfaceDatabaseClient;
use WPStaging\Framework\Filesystem\FileObject;
use WPStaging\Pro\Backup\Exceptions\DiskNotWritableException;

abstract class AbstractExporter
{
    protected $client;

    protected $database;

    protected $sourceTablePrefix;

    protected $sourceTableBasePrefix;

        protected $sourceTablePrefixLength;

    protected $file;

    protected $excludedTables = [];

    protected $subsites = [];

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->client = $database->getClient();
        $this->sourceTablePrefix = $this->getWpDb()->prefix;
        $this->sourceTableBasePrefix = $this->database->getBasePrefix();
        $this->sourceTablePrefixLength = strlen($this->sourceTablePrefix);
    }

    public function setSubsites($subsites)
    {
        $this->subsites = $subsites;
    }

    public function setTablesToExclude($tablesToExclude)
    {
        foreach ($tablesToExclude as $tableWithoutPrefix) {
            $this->excludedTables[] = $this->sourceTableBasePrefix . $tableWithoutPrefix;
            $this->addExcludedTablesForSubsites($this->sourceTableBasePrefix, $tableWithoutPrefix);
        }
    }

    protected function addExcludedTablesForSubsites($tablePrefix, $tableWithoutPrefix)
    {
        if (!is_multisite()) {
            return;
        }

        foreach ($this->subsites as $subsite) {
            $siteId = $subsite['blog_id'];
            if (empty($siteId) || $siteId === 1) {
                continue;
            }

            $tableName = $tablePrefix . $siteId . '_' . $tableWithoutPrefix;
            if (!in_array($tableName, $this->excludedTables)) {
                $this->excludedTables[] = $tableName;
            }
        }
    }

    protected function getWpDb()
    {
        return $this->database->getWpdba()->getClient();
    }

    public function setFileName($filename)
    {
        $this->file = new FileObject($filename, FileObject::MODE_APPEND);
    }

    protected function getPrefixedTableName($tableName)
    {
        return $this->replacePrefix($tableName, '{WPSTG_TMP_PREFIX}');
    }

    protected function replacePrefix($prefixedString, $newPrefix)
    {
        return $newPrefix . substr($prefixedString, $this->sourceTablePrefixLength);
    }
}
