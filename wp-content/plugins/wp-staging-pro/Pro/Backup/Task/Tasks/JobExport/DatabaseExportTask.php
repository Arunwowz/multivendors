<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use DateTime;
use Exception;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Adapter\Directory;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Service\Database\Exporter\DDLExporter;
use WPStaging\Pro\Backup\Service\Database\Exporter\RowsExporter;
use WPStaging\Pro\Backup\Task\ExportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;
use WPStaging\Pro\Backup\Dto\TaskResponseDto;
use WPStaging\Framework\Utils\Cache\Cache;

class DatabaseExportTask extends ExportTask
{
    const FILE_FORMAT = 'sql';

    const PART_IDENTIFIER = 'wpstgdb';

    /** @var Directory */
    private $directory;

    /** @var int */
    private $currentPartIndex = 0;

    public function __construct(Directory $directory, LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);
        $this->directory = $directory;
    }

    public static function getTaskName()
    {
        return 'backup_export_database';
    }

    public static function getTaskTitle()
    {
        return 'Export database';
    }

    /**
     * @return object|TaskResponseDto
     * @throws Exception
     */
    public function execute()
    {
        $this->setupDatabaseFilePathName();

        // Tables to exclude without prefix
        $tablesToExclude = [
            'wpstg_queue',
            'wpr_rucss_used_css',
        ];

        $subsites = [];
        if (is_multisite()) {
            $subsites = $this->jobDataDto->getSitesToExport();
        }

        // First request: Create DDL
        if (!$this->stepsDto->getTotal()) {
            /** @var DDLExporter */
            $ddlExporter = WPStaging::getInstance()->getContainer()->make(DDLExporter::class);
            $ddlExporter->setFileName($this->jobDataDto->getDatabaseFile());
            $ddlExporter->setSubsites($subsites);
            $ddlExporter->setTablesToExclude($tablesToExclude);
            $ddlExporter->exportDDLTablesAndViews();
            $this->jobDataDto->setTablesToExport(array_merge($ddlExporter->getTables(), $ddlExporter->getNonWPTables()));
            $this->jobDataDto->setNonWpTables($ddlExporter->getNonWPTables());

            $this->stepsDto->setTotal(count($this->jobDataDto->getTablesToExport()));

            // Early bail: DDL created, do not increase step, so that the next request can start exporting rows from the first table.
            return $this->generateResponse(false);
        }

        // Safety check: Check that the DDL was successfully created
        if (empty($this->jobDataDto->getTablesToExport())) {
            $this->logger->critical('Could not create the tables DDL.');
            throw new Exception('Could not create the tables DDL.');
        }

        // Lazy instantiation
        /** @var RowsExporter */
        $rowsExporter = WPStaging::getInstance()->getContainer()->make(RowsExporter::class);
        $rowsExporter->setSubsites($subsites);
        $rowsExporter->setupPrefixedValuesForSubsites();
        $rowsExporter->setFileName($this->jobDataDto->getDatabaseFile());
        $rowsExporter->setTables($this->jobDataDto->getTablesToExport());
        $rowsExporter->setIsBackupSplit($this->jobDataDto->getIsMultipartBackup());
        $rowsExporter->setMaxSplitSize($this->jobDataDto->getMaxMultipartBackupSize());
        $rowsExporter->setTablesToExclude($tablesToExclude);
        $rowsExporter->setNonWpTables($this->jobDataDto->getNonWpTables());

        do {
            $rowsExporter->setTableIndex($this->stepsDto->getCurrent());

            if ($rowsExporter->isTableExcluded()) {
                $this->logger->info(sprintf(
                    __('Export database: Skipped Table %s by exclusion rule', 'wp-staging'),
                    $rowsExporter->getTableBeingExported()
                ));

                $this->jobDataDto->setTotalRowsExported(0);
                $this->jobDataDto->setTableRowsOffset(0);
                $this->jobDataDto->setTableAverageRowLength(0);
                $this->stepsDto->incrementCurrentStep();

                /*
                 * Persist the steps dto, so that if memory blows while processing
                 * the next table, the next request will continue from there.
                 */
                $this->persistStepsDto();
                continue;
            }

            $rowsExporter->setTableRowsOffset($this->jobDataDto->getTableRowsOffset());
            $rowsExporter->setTotalRowsExported($this->jobDataDto->getTotalRowsExported());

            // Maybe lock the table
            $tableLocked = false;
            $hasNumericIncrementalPk = false;

            try {
                $rowsExporter->getPrimaryKey();
                $hasNumericIncrementalPk = true;
            } catch (Exception $e) {
                $tableLockResult = $rowsExporter->lockTable();
                $tableLocked = !empty($tableLockResult);
            }

            // Count rows once per table
            if ($this->jobDataDto->getTableRowsOffset() === 0) {
                $this->jobDataDto->setTotalRowsOfTableBeingExported($rowsExporter->countTotalRows());

                if ($hasNumericIncrementalPk) {
                    /*
                     * We set the offset to the lowest number possible, so that we can start fetching
                     * rows even if their primary key values are a negative integer or zero.
                     */
                    $rowsExporter->setTableRowsOffset(-PHP_INT_MAX);
                }
            }

            $rowsExporter->setTotalRowsInCurrentTable($this->jobDataDto->getTotalRowsOfTableBeingExported());

            try {
                $rowsLeftToExport = $rowsExporter->export($this->jobDataDto->getId(), $this->logger);

                if ($tableLocked) {
                    $rowsExporter->unLockTables();
                }
            } catch (Exception $e) {
                if ($tableLocked) {
                    $rowsExporter->unLockTables();
                }

                $this->logger->critical($e->getMessage());
                throw $e;
            }

            $this->stepsDto->setCurrent($rowsExporter->getTableIndex());
            $this->jobDataDto->setTotalRowsExported($rowsExporter->getTotalRowsExported());
            $this->jobDataDto->setTableRowsOffset($rowsExporter->getTableRowsOffset());

            $this->logger->info(sprintf(
                __('Export database: Table %s. Rows: %s/%s', 'wp-staging'),
                $rowsExporter->getTableBeingExported(),
                number_format_i18n($rowsExporter->getTotalRowsExported()),
                number_format_i18n($this->jobDataDto->getTotalRowsOfTableBeingExported())
            ));

            $this->logger->debug(sprintf(
                __('Export database: Table %s. Query time: %s Batch Size: %s last query json: %s', 'wp-staging'),
                $rowsExporter->getTableBeingExported(),
                $this->jobDataDto->getDbRequestTime(),
                $this->jobDataDto->getBatchSize(),
                $this->jobDataDto->getLastQueryInfoJSON()
            ));

            // Done with this table.
            if ($rowsLeftToExport === 0) {
                $this->jobDataDto->setTotalRowsExported(0);
                $this->jobDataDto->setTableRowsOffset(0);
                $this->jobDataDto->setTableAverageRowLength(0);
                $this->stepsDto->incrementCurrentStep();

                /*
                 * Persist the steps dto, so that if memory blows while processing
                 * the next table, the next request will continue from there.
                 */
                $this->persistStepsDto();
            }

            if ($rowsExporter->doExceedSplitSize()) {
                $this->jobDataDto->setMaxDbPartIndex($this->currentPartIndex + 1);
                return $this->generateResponse(false);
            }
        } while (!$this->isThreshold() && !$this->stepsDto->isFinished());

        return $this->generateResponse(false);
    }

    private function setupDatabaseFilePathName()
    {
        if (!$this->jobDataDto->getIsMultipartBackup()) {
            if ($this->jobDataDto->getDatabaseFile()) {
                return;
            }

            $basename = $this->getDatabaseFilename();
            $this->jobDataDto->setDatabaseFile($this->directory->getCacheDirectory() . $basename);
            return;
        }

        $this->currentPartIndex = $this->jobDataDto->getMaxDbPartIndex();
        $basename = $this->getDatabaseFilename($this->currentPartIndex);

        $databaseFileLocation = $this->directory->getCacheDirectory() . $basename;
        // create database file with comments for parts
        if (!file_exists($databaseFileLocation) && $this->currentPartIndex !== 0) {
            $this->createDatabasePart($databaseFileLocation, $this->currentPartIndex);
        }

        $actions = $this->jobDataDto->getMoveBackupPartsActions();
        if (array_key_exists($basename, $actions)) {
            return;
        }

        $this->jobDataDto->setDatabaseFile($databaseFileLocation);
        $this->jobDataDto->pushMoveBackupPartsAction($databaseFileLocation, $basename);
    }

    /**
     * @param string $databaseFileLocation
     * @param int    $partNo
     */
    private function createDatabasePart($databaseFileLocation, $partNo)
    {
        global $wpdb;

        $content = <<<SQL
-- WP Staging SQL Export Dump
-- https://wp-staging.com/
--
-- Host: {$wpdb->dbhost}
-- Database: {$wpdb->dbname}
-- Part No: {$partNo}
-- Class: WPStaging\Pro\Backup\Service\Database\Exporter\RowsExporter
--

SQL;
        file_put_contents($databaseFileLocation, $content);
    }

    private function getDatabaseFilename($partIndex = 0)
    {
        $identifier = self::PART_IDENTIFIER;
        if ($partIndex > 0) {
            $identifier .= '.' . $partIndex;
        }

        global $wpdb;

        return sprintf(
            '%s_%s.%s.%s.%s',
            parse_url(get_home_url())['host'],
            $this->getJobId(),
            rtrim($wpdb->base_prefix, '_-'),
            $identifier,
            self::FILE_FORMAT
        );
    }
}
