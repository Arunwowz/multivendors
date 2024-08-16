<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use WPStaging\Pro\Backup\Task\FileExportTask;

class ExportMuPluginsTask extends FileExportTask
{
    const IDENTIFIER = 'muplugins';

    public static function getTaskName()
    {
        return parent::getTaskName() . '_' . self::IDENTIFIER;
    }

    public static function getTaskTitle()
    {
        return 'Adding Mu-Plugins to Backup';
    }

    protected function getFileIdentifier()
    {
        return self::IDENTIFIER;
    }
}
