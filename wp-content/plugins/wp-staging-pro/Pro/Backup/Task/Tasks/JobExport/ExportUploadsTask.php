<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobExport;

use WPStaging\Pro\Backup\Task\FileExportTask;

class ExportUploadsTask extends FileExportTask
{
    const IDENTIFIER = 'uploads';

    public static function getTaskName()
    {
        return parent::getTaskName() . '_' . self::IDENTIFIER;
    }

    public static function getTaskTitle()
    {
        return 'Adding Medias to Backup';
    }

    protected function getFileIdentifier()
    {
        return self::IDENTIFIER;
    }
}
