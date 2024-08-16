<?php

namespace WPStaging\Pro\Backup\Task;

use WPStaging\Pro\Backup\Ajax\Import\PrepareImport;
use WPStaging\Pro\Backup\Dto\Job\JobImportDataDto;
use WPStaging\Pro\Backup\Dto\JobDataDto;

abstract class ImportTask extends AbstractTask
{

    /** @var JobImportDataDto */
    protected $jobDataDto;

    public function setJobDataDto(JobDataDto $jobDataDto)
    {
        /** @var JobImportDataDto $jobDataDto */
        if (
            $jobDataDto->getBackupMetadata()->getIsExportingDatabase()
            && !$jobDataDto->getBackupMetadata()->getIsExportingMuPlugins()
            && !$jobDataDto->getBackupMetadata()->getIsExportingOtherWpContentFiles()
            && !$jobDataDto->getBackupMetadata()->getIsExportingPlugins()
            && !$jobDataDto->getBackupMetadata()->getIsExportingThemes()
            && !$jobDataDto->getBackupMetadata()->getIsExportingUploads()
        ) {
            $jobDataDto->setDatabaseOnlyBackup(true);
        }

        parent::setJobDataDto($jobDataDto);
    }

    /**
     * @param string $table
     * @param string $prefix
     *
     * @return string
     */
    public function addShortNameTable($table, $prefix)
    {
        $shortName = uniqid($prefix) . str_pad(rand(0, 999999), 6, '0');
        if ($prefix === PrepareImport::TMP_DATABASE_PREFIX) {
            $this->jobDataDto->addShortNameTableToImport($table, $shortName);
        } elseif ($prefix === PrepareImport::TMP_DATABASE_PREFIX_TO_DROP) {
            $this->jobDataDto->addShortNameTableToDrop($table, $shortName);
        }

        return $shortName;
    }

    /**
     * @param string $table
     * @param string $prefix
     *
     * @return string
     */
    public function getShortNameTable($table, $prefix)
    {
        $shortTables = [];
        if ($prefix === PrepareImport::TMP_DATABASE_PREFIX) {
            $shortTables = $this->jobDataDto->getShortNamesTablesToImport();
        } elseif ($prefix === PrepareImport::TMP_DATABASE_PREFIX_TO_DROP) {
            $shortTables = $this->jobDataDto->getShortNamesTablesToDrop();
        }

        return array_search($table, $shortTables);
    }

    /**
     * @param string $table
     * @param string $prefix
     *
     * @return string
     */
    public function getFullNameTableFromShortName($table, $prefix)
    {
        $shortTables = [];
        if ($prefix === PrepareImport::TMP_DATABASE_PREFIX) {
            $shortTables = $this->jobDataDto->getShortNamesTablesToImport();
        } elseif ($prefix === PrepareImport::TMP_DATABASE_PREFIX_TO_DROP) {
            $shortTables = $this->jobDataDto->getShortNamesTablesToDrop();
        }

        if (!array_key_exists($table, $shortTables)) {
            return $table;
        }

        return $shortTables[$table];
    }
}
