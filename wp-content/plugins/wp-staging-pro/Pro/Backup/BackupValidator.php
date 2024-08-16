<?php

namespace WPStaging\Pro\Backup;

use WPStaging\Pro\Backup\Entity\BackupMetadata;
use WPStaging\Pro\Backup\Service\BackupsFinder;

class BackupValidator
{
    /** @var BackupsFinder */
    private $backupsFinder;

    /** @var array */
    protected $missingPartIssues = [];

    /** @var array */
    protected $partSizeIssues = [];

    /** @var string */
    protected $backupDir;

    /** @var array  */
    protected $existingParts = [];

    public function __construct(BackupsFinder $backupsFinder)
    {
        $this->partSizeIssues = [];
        $this->missingPartIssues = [];
        $this->backupsFinder = $backupsFinder;
        $this->backupDir = '';
    }

    /** @return array  */
    public function getExistingParts()
    {
        return $this->existingParts;
    }

    /** @return array */
    public function getMissingPartIssues()
    {
        return $this->missingPartIssues;
    }

    /** @return array */
    public function getPartSizeIssues()
    {
        return $this->partSizeIssues;
    }

    /** @return bool */
    public function checkIfSplitBackupIsValid(BackupMetadata $metadata)
    {
        $this->partSizeIssues = [];
        $this->missingPartIssues = [];

        // Early bail if not split backup
        if (!$metadata->getIsMultipartBackup()) {
            return true;
        }

        $this->backupDir = wp_normalize_path($this->backupsFinder->getBackupsDirectory());

        $splitMetadata = $metadata->getMultipartMetadata();

        foreach ($splitMetadata->getPluginsParts() as $part) {
            $this->validatePart($part, 'plugins');
        }

        foreach ($splitMetadata->getThemesParts() as $part) {
            $this->validatePart($part, 'themes');
        }

        foreach ($splitMetadata->getUploadsParts() as $part) {
            $this->validatePart($part, 'uploads');
        }

        foreach ($splitMetadata->getMuPluginsParts() as $part) {
            $this->validatePart($part, 'muplugins');
        }

        foreach ($splitMetadata->getOthersParts() as $part) {
            $this->validatePart($part, 'others');
        }

        foreach ($splitMetadata->getDatabaseParts() as $part) {
            $this->validatePart($part, 'database');
        }

        return empty($this->partSizeIssues) && empty($this->missingPartIssues);
    }

    /**
     * @param array $part contains part name, size and md5 hash of file
     * @param string $type (plugins|themes|uploads|muplugins|others|database)
     *
     * @return bool
     */
    private function validatePart($part, $type)
    {
        $path = $this->backupDir . str_replace($this->backupDir, '', wp_normalize_path(untrailingslashit($part)));
        if (!file_exists($path)) {
            /*
            if ($type !== 'database') {
                return true;
            }
            */

            $this->missingPartIssues[] = [
                'name' => $part,
                'type' => $type
            ];

            return false;
        }

        $metadata = new BackupMetadata();
        $metadata = $metadata->hydrateByFilePath($path);

        if (filesize($path) !== $metadata->getMultipartMetadata()->getPartSize()) {
            $this->partSizeIssues[] = $part;
            return false;
        }

        $this->existingParts[] = $part;
        return true;
    }
}
