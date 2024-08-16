<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use WPStaging\Pro\Backup\Task\FileExportTask;

class ExportThemesTask extends FileExportTask
{
    const IDENTIFIER = 'themes';

    public static function getTaskName()
    {
        return parent::getTaskName() . '_' . self::IDENTIFIER;
    }

    public static function getTaskTitle()
    {
        return 'Adding Themes to Backup';
    }

    protected function getFileIdentifier()
    {
        return self::IDENTIFIER;
    }
}
