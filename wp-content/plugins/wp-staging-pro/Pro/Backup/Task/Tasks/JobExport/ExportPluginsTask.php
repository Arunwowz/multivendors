<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use WPStaging\Pro\Backup\Task\FileExportTask;

class ExportPluginsTask extends FileExportTask
{
    const IDENTIFIER = 'plugins';

    public static function getTaskName()
    {
        return parent::getTaskName() . '_' . self::IDENTIFIER;
    }

    public static function getTaskTitle()
    {
        return 'Adding Plugins to Backup';
    }

    protected function getFileIdentifier()
    {
        return self::IDENTIFIER;
    }
}
