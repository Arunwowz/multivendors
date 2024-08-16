<?php

namespace WPStaging\Pro\Backup\Storage\Storages\SFTP;

use Exception;
use WPStaging\Framework\Filesystem\FileObject;
use WPStaging\Framework\Queue\FinishedQueueException;
use WPStaging\Framework\Utils\Strings;
use WPStaging\Pro\Backup\Dto\Job\JobExportDataDto;
use WPStaging\Pro\Backup\Exceptions\StorageException;
use WPStaging\Pro\Backup\Storage\RemoteUploaderInterface;
use WPStaging\Pro\Backup\Storage\Storages\SFTP\Auth;
use WPStaging\Pro\Backup\WithBackupIdentifier;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

use function WPStaging\functions\debug_log;

class Uploader implements RemoteUploaderInterface
{
    use WithBackupIdentifier;

    /** @var JobExportDataDto */
    private $jobDataDto;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $filePath;

    /** @var string */
    private $fileName;

    /** @var string */
    private $path;

    /** @var string */
    private $remotePath;

    /** @var int */
    private $maxBackupsToKeep;

    /** @var FileObject */
    private $fileObject;

    /** @var int */
    private $chunkSize;

    /** @var Auth */
    private $auth;

    /** @var ClientInterface */
    private $client;

    /** @var bool|string */
    private $error;

    /** @var Strings */
    private $strings;

    public function __construct(Auth $auth, Strings $strings)
    {
        $this->error = false;
        $this->auth = $auth;
        $this->strings = $strings;
        if (!$this->auth->isAuthenticated()) {
            $this->error = __('FTP / SFTP service is not authenticated. Backup is still available locally.', 'wp-staging');
            return;
        }

        $this->client = $auth->getClient();
        $options = $this->auth->getOptions();
        $this->path = !empty($options['location']) ? trailingslashit($options['location']) : '';
        $this->maxBackupsToKeep = isset($options['maxBackupsToKeep']) ? $options['maxBackupsToKeep'] : 15;
        $this->maxBackupsToKeep = intval($this->maxBackupsToKeep);
        $this->maxBackupsToKeep = $this->maxBackupsToKeep > 0 ? $this->maxBackupsToKeep : 15;
    }

    public function getProviderName()
    {
        return 'SFTP / FTP';
    }

    /**
     * @param int $backupSize
     * @throws DiskNotWritableException
     */
    public function checkDiskSize($backupSize)
    {
        //no-op
    }

    public function setupUpload(LoggerInterface $logger, JobExportDataDto $jobDataDto, $chunkSize = 1 * 1024 * 1024)
    {
        $this->logger = $logger;
        $this->jobDataDto = $jobDataDto;
        $this->chunkSize = $chunkSize;
    }

    public function setBackupFilePath($backupFilePath, $fileName)
    {
        $this->fileName = $fileName;
        $this->filePath = $backupFilePath;
        $this->fileObject = new FileObject($this->filePath, FileObject::MODE_READ);
        $this->remotePath = $this->path . $this->fileObject->getBasename();

        if (!$this->client->login()) {
            $this->error = 'Unable to connect to ' . $this->getProviderName();
            return false;
        }

        $uploadMetadata = $this->jobDataDto->getRemoteStorageMeta();
        if (!array_key_exists($this->fileName, $uploadMetadata)) {
            $this->setMetadata(0);
            $this->logger->info('Starting upload of file:' . $this->fileName);
            return true;
        }

        return true;
    }

    /**
     * @return int
     */
    public function chunkUpload()
    {
        $fileMetadata = $this->jobDataDto->getRemoteStorageMeta()[$this->fileName];
        $offset = $fileMetadata['Offset'];

        $this->fileObject->fseek($offset);
        $chunk = $this->fileObject->fread($this->chunkSize);

        $chunkSize = strlen($chunk);
        try {
            $this->client->upload($this->path, $this->fileName, $chunk, $offset);
            $offset += $chunkSize;
        } catch (StorageException $ex) {
            throw new StorageException($ex->getMessage());
        } catch (Exception $ex) {
            debug_log("Error: " . $ex->getMessage());
        }

        if ($offset >= $this->fileObject->getSize()) {
            throw new FinishedQueueException();
        }

        $this->setMetadata($offset);
        return $chunkSize;
    }

    /**
     * @param string $filePath
     * @return bool
     */
    public function uploadFile($filePath)
    {
        $fileObject = new FileObject($filePath, FileObject::MODE_READ);

        $fileObject->fseek(0);
        $chunk = $fileObject->fread($fileObject->getSize());

        try {
            if (!$this->client->login()) {
                debug_log("Error: " . $this->client->getError());
                return false;
            }

            $this->client->upload($this->path, $fileObject->getBasename(), $chunk, 0);
            $this->client->close();
        } catch (StorageException $ex) {
            debug_log("Error: " . $ex->getMessage());
            return false;
        } catch (Exception $ex) {
            debug_log("Error: " . $ex->getMessage());
            return false;
        }

        return true;
    }

    public function stopUpload()
    {
        $this->client->close();
    }

    public function getError()
    {
        return $this->error;
    }

    public function getBackups()
    {
        if ($this->client === false) {
            $this->error = 'Unable to Initiate a Client';
            return [];
        }

        if (!$this->client->login()) {
            $this->error = "Unable to connect to " . $this->client->getError();
            return [];
        }

        try {
            $files = $this->client->getFiles($this->path);
            if (!is_array($files)) {
                $this->error = $this->client->getError() . ' - ' . __('Unable to fetch existing backups for cleanup', 'wp-staging');
                return [];
            }

            $backups = [];
            foreach ($files as $file) {
                if ($this->strings->endsWith($file['name'], '.wpstg') || $this->strings->endsWith($file['name'], '.sql')) {
                    $backups[] = $file;
                }
            }

            return $backups;
        } catch (Exception $ex) {
            $this->error = $ex->getMessage();
            return [];
        }
    }

    public function deleteOldestBackups()
    {
        if ($this->client === false) {
            $this->error = 'Unable to Initiate a Client';
            return false;
        }

        if (!$this->client->login()) {
            $this->error = "Unable to connect to " . $this->client->getError();
            return [];
        }

        try {
            $files = $this->getBackups();

            if (count($files) < $this->maxBackupsToKeep) {
                return true;
            }

            $backups = [];
            /**
             * arrange the backup in the format key value format to make it easy to delete
             * Extract the id of the backup from the file
             */
            foreach ($files as $file) {
                $fileName = str_replace($this->path, '', $file['name']);
                $backupId = $this->extractBackupIdFromFilename($fileName);
                if (!array_key_exists($backupId, $backups)) {
                    $backups[$backupId] = [];
                }

                if (!$this->isBackupPart($fileName)) {
                    $backups[$backupId]['id'] = $file['name'];
                    continue;
                }

                if (!array_key_exists('parts', $backups[$backupId])) {
                    $backups[$backupId]['parts'] = [];
                }

                $backups[$backupId]['parts'][] = $file['name'];
            }

            $backupsToDelete = count($backups) - $this->maxBackupsToKeep;
            foreach ($backups as $backup) {
                if ($backupsToDelete < 0) {
                    return true;
                }

                $this->client->setPath($this->path);
                $this->client->deleteFile($backup['id']);
                if (array_key_exists('parts', $backup)) {
                    foreach ($backup['parts'] as $part) {
                        $result = $this->client->deleteFile($part);
                        if ($result === false) {
                            $this->error = $this->client->getError();
                            return false;
                        }
                    }
                }

                $backupsToDelete--;
            }

            return true;
        } catch (Exception $ex) {
            debug_log("Delete oldest backup");
            $this->error = $ex->getMessage();
            return false;
        }
    }

    public function verifyUploads($uploadsToVerify)
    {
        if (!$this->client->login()) {
            $this->error = "Unable to connect to " . $this->client->getError();
            return false;
        }

        $files = $this->client->getFiles($this->path);
        $this->client->close();
        if (!is_array($files)) {
            $this->error = $this->client->getError() . ' - ' . __('Unable to fetch existing backups for verification', 'wp-staging');
            return false;
        };

        $uploadsConfirmed = [];
        foreach ($files as $file) {
            $fileName = str_replace($this->path, '', $file['name']);
            if (array_key_exists($fileName, $uploadsToVerify) && (is_null($file['size']) || $uploadsToVerify[$fileName] === $file['size'])) {
                $uploadsConfirmed[] = $fileName;
            }
        }

        return count($uploadsConfirmed) === count($uploadsToVerify);
    }

    protected function setMetadata($offset = 0)
    {
        $this->jobDataDto->setRemoteStorageMeta([
            $this->fileName => [
                'Offset' => $offset,
            ]
        ]);
    }
}
