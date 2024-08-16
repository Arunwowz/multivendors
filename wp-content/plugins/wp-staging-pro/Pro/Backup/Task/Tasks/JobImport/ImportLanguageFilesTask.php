<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobImport;

use WPStaging\Framework\Filesystem\PathIdentifier;
use WPStaging\Pro\Backup\Task\FileImportTask;

class ImportLanguageFilesTask extends FileImportTask
{
    public static function getTaskName()
    {
        return 'backup_restore_language_files';
    }

    public static function getTaskTitle()
    {
        return 'Restoring Language Files';
    }

    /**
     * @inheritDoc
     */
    protected function getParts()
    {
        return [];
    }

    protected function buildQueue()
    {
        try {
            $languageFiles = $this->getLanguageFiles();
        } catch (\Exception $e) {
            // Folder does not exist. Likely there are no language files in wp-content to import.
            $languageFiles = [];
        }

        $destinationDir = $this->directory->getLangsDirectory();

        foreach ($languageFiles as $relativeLangPath => $absoluteLangPath) {
            /*
             * Scenario: Importing another file that exists or do not exist
             * 1. Overwrite conflicting files with what's in the backup
             */
            $this->enqueueMove($absoluteLangPath, $destinationDir . $relativeLangPath);
        }
    }

    /**
     * @return array An array of paths of language files found in a language subfolder of root of the temporary extracted wp-content backup folder,
     *               where the index is the relative path, and the value it's absolute path.
     * @example [
     *              'languages/edd.php' => '/var/www/single/wp-content/uploads/wp-staging/tmp/import/655bb61a54f5/wpstg_l_/edd.php'
     *          ]
     *
     */
    private function getLanguageFiles()
    {
        $path = $this->jobDataDto->getTmpDirectory() . PathIdentifier::IDENTIFIER_LANG;
        $path = trailingslashit($path);

        return $this->filesystem->findFilesInDir($path);
    }
}
