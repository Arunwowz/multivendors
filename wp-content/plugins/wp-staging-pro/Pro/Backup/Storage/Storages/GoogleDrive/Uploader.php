<?php

namespace WPStaging\Pro\Backup\Storage\Storages\GoogleDrive;

use Exception;
use WPStaging\Framework\Filesystem\FileObject;
use WPStaging\Framework\Queue\FinishedQueueException;
use WPStaging\Framework\Utils\Strings;
use WPStaging\Pro\Backup\Dto\Job\JobExportDataDto;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Exceptions\DiskNotWritableException;
use WPStaging\Pro\Backup\Storage\RemoteUploaderInterface;
use WPStaging\Pro\Backup\WithBackupIdentifier;
use WPStaging\Vendor\Google\Client as GoogleClient;
use WPStaging\Vendor\Google\Service\Drive as GoogleDriveService;
use WPStaging\Vendor\Google\Service\Drive\DriveFile as GoogleDriveFile;
use WPStaging\Vendor\Google\Http\MediaFileUpload as GoogleMediaFileUpload;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

use function WPStaging\functions\debug_log;

class Uploader implements RemoteUploaderInterface
{
    use WithBackupIdentifier;

    /** @var GoogleClient */
    private $client;

    /** @var JobExportDataDto */
    private $jobDataDto;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $filePath;

    /** @var string */
    private $fileName;

    /** @var string|null */
    private $folderId;

    /** @var string */
    private $folderName;

    /** @var int */
    private $maxBackupsToKeep;

    /** @var GoogleDriveService */
    private $service;

    /** @var FileObject */
    private $fileObject;

    /** @var GoogleMediaFileUpload */
    private $media;

    /** @var int */
    private $chunkSize;

    /** @var Auth */
    private $auth;

    /** @var bool|string */
    private $error;

    /** @var Strings */
    private $strings;

    public function __construct(Auth $auth, Strings $strings)
    {
        $this->error = false;
        $this->auth = $auth;
        $this->strings = $strings;

        if (!$this->auth->isGuzzleAvailable()) {
            $this->error = __('cURL extension is missing. Backup is still available locally.', 'wp-staging');
            return;
        }

        if (!$this->auth->isAuthenticated()) {
            $this->error = __('Google Drive is not authenticated. Backup is still available locally.', 'wp-staging');
            return;
        }

        $this->client = $auth->setClientWithAuthToken();
        $options = $this->auth->getOptions();
        $this->folderName = isset($options['folderName']) ? $options['folderName'] : Auth::FOLDER_NAME;
        $this->maxBackupsToKeep = isset($options['maxBackupsToKeep']) ? $options['maxBackupsToKeep'] : 15;
        $this->maxBackupsToKeep = intval($this->maxBackupsToKeep);
        $this->maxBackupsToKeep = $this->maxBackupsToKeep > 0 ? $this->maxBackupsToKeep : 15;
        $this->folderId = null;
    }

    public function getProviderName()
    {
        return 'Google Drive';
    }

    public function setupUpload(LoggerInterface $logger, JobExportDataDto $jobDataDto, $chunkSize = 1 * 1024 * 1024)
    {
        $this->logger = $logger;
        $this->jobDataDto = $jobDataDto;
        $this->chunkSize = $chunkSize;
    }

    /**
     * @param int $backupSize
     * @throws DiskNotWritableException
     */
    public function checkDiskSize($backupSize)
    {
        $this->service = new GoogleDriveService($this->client);

        if (!$this->doExceedGoogleDiskLimit($backupSize)) {
            throw new DiskNotWritableException($this->error);
        }
    }

    /** @return bool */
    public function setBackupFilePath($backupFilePath, $fileName)
    {
        $this->fileName = $fileName;
        $this->filePath = $backupFilePath;
        $this->fileObject = new FileObject($this->filePath, FileObject::MODE_READ);
        $this->service = new GoogleDriveService($this->client);

        $this->folderId = $this->auth->getFolderIdByName($this->folderName, $this->service);

        $fileMetadata = new GoogleDriveFile([
            'name' => $fileName,
            'parents' => [$this->folderId],
        ]);

        $this->client->setDefer(true);

        $request = $this->service->files->create($fileMetadata);
        $this->media = new GoogleMediaFileUpload(
            $this->client,
            $request,
            'application/octet-stream',
            null,
            true,
            $this->chunkSize
        );

        $this->media->setFileSize($this->fileObject->getSize());

        $uploadMetadata = $this->jobDataDto->getRemoteStorageMeta();
        if (!array_key_exists($this->fileName, $uploadMetadata)) {
            $this->setMetadata($this->media->getResumeUri(), 0);
            $this->logger->info('Starting upload of file:' . $this->fileName);
            return true;
        }

        $fileMetadata = $uploadMetadata[$this->fileName];

        $resumeURI = $fileMetadata['ResumeURI'];
        $this->media->resume($resumeURI);
        $newResumeURI = $this->media->getResumeUri();
        if ($newResumeURI !== $resumeURI) {
            $this->setMetadata($newResumeURI, $fileMetadata['Offset']);
        }

        return true;
    }

    /**
     * @param string $filePath
     * @param StepsDto $stepsDto
     * @param int $chunkSize
     *
     * @return int
     */
    public function chunkUpload()
    {
        $status = false;
        $fileMetadata = $this->jobDataDto->getRemoteStorageMeta()[$this->fileName];
        $offset = $fileMetadata['Offset'];

        $this->fileObject->fseek($offset);
        $chunk = $this->fileObject->fread($this->chunkSize);
        $status = $this->media->nextChunk($chunk);

        $chunkSize = strlen($chunk);
        $offset += $chunkSize;

        if ($status !== false) {
            throw new FinishedQueueException();
        }

        $this->setMetadata($fileMetadata['ResumeURI'], $offset);
        return $chunkSize;
    }

        /**
     * @param string $filePath
     * @return bool
     */
    public function uploadFile($filePath)
    {
        $fileObject = new FileObject($filePath, FileObject::MODE_READ);

        $this->service = new GoogleDriveService($this->client);

        if ($this->folderId === null) {
            $this->folderId = $this->auth->getFolderIdByName($this->folderName);
        }

        $fileMetadata = new GoogleDriveFile([
            'name' => $fileObject->getBasename(),
            'parents' => [$this->folderId],
        ]);

        $this->client->setDefer(true);

        $request = $this->service->files->create($fileMetadata);
        $this->media = new GoogleMediaFileUpload(
            $this->client,
            $request,
            'application/octet-stream',
            null,
            true,
            $fileObject->getSize()
        );

        $this->media->setFileSize($fileObject->getSize());

        $fileObject->fseek(0);
        $chunk = $fileObject->fread($fileObject->getSize());

        try {
            $this->media->nextChunk($chunk);
        } catch (Exception $ex) {
            //debug_log("Error: " . $ex->getMessage());
            return false;
        }

        return true;
    }

    public function stopUpload()
    {
        $this->client->setDefer(false);
    }

    /** @return string */
    public function getError()
    {
        return $this->error;
    }

    public function getBackups()
    {
        $files = $this->auth->getFiles();

        $backups = [];
        foreach ($files as $file) {
            if ($this->strings->endsWith($file->getName(), '.wpstg') || $this->strings->endsWith($file->getName(), '.sql')) {
                $backups[] = $file;
            }
        }

        return $backups;
    }

    public function deleteOldestBackups()
    {
        $backupsFiles = $this->getBackups();

        if (count($backupsFiles) < $this->maxBackupsToKeep) {
            return true;
        }

        $backups = [];
        /**
         * arrange the backup in the format key value format to make it easy to delete
         * Extract the id of the backup from the file
         */
        foreach ($backupsFiles as $file) {
            $fileName = $file->getName();
            $backupId = $this->extractBackupIdFromFilename($fileName);
            if (!array_key_exists($backupId, $backups)) {
                $backups[$backupId] = [];
            }

            if (!$this->isBackupPart($fileName)) {
                $backups[$backupId]['id'] = $file->getId();
                continue;
            }

            if (!array_key_exists('parts', $backups[$backupId])) {
                $backups[$backupId]['parts'] = [];
            }

            $backups[$backupId]['parts'][] = $file->getId();
        }

        $backupsToDelete = count($backups) - $this->maxBackupsToKeep;
        $this->service = new GoogleDriveService($this->client);

        foreach ($backups as $backup) {
            if ($backupsToDelete < 0) {
                return true;
            }

            $this->service->files->delete($backup['id']);
            if (array_key_exists('parts', $backup)) {
                foreach ($backup['parts'] as $part) {
                    $this->service->files->delete($part);
                }
            }

            $backupsToDelete--;
        }

        return true;
    }

    public function verifyUploads($uploadsToVerify)
    {
        $files = $this->auth->getFiles();
        $uploadsConfirmed = [];
        foreach ($files as $file) {
            $fileName = $file->getName();
            $fileSize = (int)$file->getSize();

            if (array_key_exists($fileName, $uploadsToVerify) && ($uploadsToVerify[$fileName] === $fileSize)) {
                $uploadsConfirmed[] = $fileName;
            }
        }

        return count($uploadsConfirmed) === count($uploadsToVerify);
    }

    protected function setMetadata($resumeURI, $offset)
    {
        $this->jobDataDto->setRemoteStorageMeta([
            $this->fileName => [
                'ResumeURI' => $resumeURI,
                'Offset' => $offset,
            ]
        ]);
    }

    /**
     * @param int $backupSize
     * @param GoogleDriveService $service
     * @return bool
     */
    private function doExceedGoogleDiskLimit($backupSize, $service = null)
    {
        if (apply_filters('wpstg.googleDrive.bypassDiskSpace', false)) {
            return true;
        }

        if ($service === null) {
            $service = $this->service;
        }

        try {
            $storage = $this->auth->getStorageInfo($service);
            $totalQuota = $storage->getLimit();
            $usedQuota = $storage->getUsage();
        } catch (Exception $ex) {
            return true;
        }

        if (!is_numeric($totalQuota) || !is_numeric($usedQuota)) {
            $this->logger->warning('Unable to get size of used or available storage space. Continuing with Upload to Google Drive!');
            return true;
        }

        $availableQuota = $totalQuota - $usedQuota;
        if (empty($availableQuota) || !is_numeric($availableQuota) || $availableQuota < 0) {
            return true;
        }

        if ($backupSize > $availableQuota) {
            $this->error = sprintf(__('Could not upload backup to Google Drive. Reason: Disk Quota Exceeded. Increase google drive space or delete old data! Backup Size: %s. Space Available: %s. Backup is still available locally.', 'wp-staging'), size_format($this->fileObject->getSize(), 2), size_format($availableQuota, 2));
            return false;
        }

        return true;
    }
}
