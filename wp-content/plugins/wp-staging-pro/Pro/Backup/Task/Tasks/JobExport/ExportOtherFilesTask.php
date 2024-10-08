<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use WPStaging\Pro\Backup\Task\FileExportTask;

class ExportOtherFilesTask extends FileExportTask
{
    /**
     * Changed to otherfiles from other because sanitize_file_name rename other.wpstg to other_.wpstg,
     * Which causes issues for Backup Upload where sanitize_file_name is used.
     */
    const IDENTIFIER = 'otherfiles';

    public static function getTaskName()
    {
        return parent::getTaskName() . '_' . self::IDENTIFIER;
    }

    public static function getTaskTitle()
    {
        return 'Adding Other Files to Backup';
    }

    protected function getFileIdentifier()
    {
        return self::IDENTIFIER;
    }
}
