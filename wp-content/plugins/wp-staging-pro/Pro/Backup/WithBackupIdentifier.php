<?php

namespace WPStaging\Pro\Backup;

use WPStaging\Pro\Backup\Task\Tasks\JobExport\DatabaseExportTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportMuPluginsTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportOtherFilesTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportPluginsTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportThemesTask;
use WPStaging\Pro\Backup\Task\Tasks\JobExport\ExportUploadsTask;

trait WithBackupIdentifier
{
    protected $listedMultipartBackups = [];

    /**
     * @param string $identifier
     * @param string $input
     * @return bool
     */
    public function checkPartByIdentifier($identifier, $input)
    {
        return preg_match("#{$identifier}(.[0-9]+)?.wpstg$#", $input);
    }

    public function isBackupPart($name)
    {
        $dbExtension = DatabaseExportTask::FILE_FORMAT;
        $dbIdentifier = DatabaseExportTask::PART_IDENTIFIER;
        if (preg_match("#{$dbIdentifier}(.[0-9]+)?.{$dbExtension}$#", $name)) {
            return true;
        }

        $pluginIdentifier = ExportPluginsTask::IDENTIFIER;
        $mupluginIdentifier = ExportMuPluginsTask::IDENTIFIER;
        $themeIdentifier = ExportThemesTask::IDENTIFIER;
        $uploadIdentifier = ExportUploadsTask::IDENTIFIER;
        $otherIdentifier = ExportOtherFilesTask::IDENTIFIER;
        if ($this->checkPartByIdentifier("({$pluginIdentifier}|{$mupluginIdentifier}|{$themeIdentifier}|{$uploadIdentifier}|{$otherIdentifier})", $name)) {
            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    public function clearListedMultipartBackups()
    {
        $this->listedMultipartBackups = [];
    }

    public function isListedMultipartBackup($filename, $shouldAddBackup = true)
    {
        $id = $this->extractBackupIdFromFilename($filename);
        if (in_array($id, $this->listedMultipartBackups)) {
            return true;
        }

        if ($shouldAddBackup) {
            $this->listedMultipartBackups[] = $id;
        }

        return false;
    }

    /**
     * @var string $filename
     * @return string
     */
    public function extractBackupIdFromFilename($filename)
    {
        $fileInfos = explode('_', $filename);
        $fileInfos = $fileInfos[count($fileInfos) - 1];
        return explode('.', $fileInfos)[0];
    }
}
