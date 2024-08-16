<?php

namespace WPStaging\Pro\Backup\Storage\Storages\BaseS3;

use Exception;
use WPStaging\Framework\Filesystem\FileObject;
use WPStaging\Framework\Queue\FinishedQueueException;
use WPStaging\Framework\Utils\Strings;
use WPStaging\Pro\Backup\Dto\Job\JobExportDataDto;
use WPStaging\Pro\Backup\Exceptions\DiskNotWritableException;
use WPStaging\Pro\Backup\Exceptions\StorageException;
use WPStaging\Pro\Backup\Storage\RemoteUploaderInterface;
use WPStaging\Pro\Backup\WithBackupIdentifier;
use WPStaging\Vendor\Aws\S3\Exception\S3Exception;
use WPStaging\Vendor\Aws\S3\S3Client;
use WPStaging\Vendor\Psr\Log\LoggerInterface;

use function WPStaging\functions\debug_log;

abstract class S3Uploader implements RemoteUploaderInterface
{
    use WithBackupIdentifier;

    /** @var JobExportDataDto */
    private $jobDataDto;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $bucketName;

    /** @var string */
    private $path;

    /** @var string */
    private $objectKey;

    /** @var int */
    private $maxBackupsToKeep;

    /** @var FileObject */
    private $fileObject;

    /** @var string */
    private $filePath;

    /** @var string */
    private $fileName;

    /** @var int */
    private $chunkSize;

    /** @var S3Auth */
    private $auth;

    /** @var S3Client */
    private $client;

    /** @var bool|string */
    private $error;

    /** @var Strings */
    private $strings;

    /** @var bool */
    private $isObjectLocked = false;

    public function __construct(S3Auth $auth, Strings $strings)
    {
        $this->error = false;
        $this->auth = $auth;
        $this->strings = $strings;

        if (!$this->auth->isGuzzleAvailable()) {
            $this->error = __('cURL extension is missing. Backup is still available locally.', 'wp-staging');
            return;
        }

        if (!$this->auth->isAuthenticated()) {
            $this->error = $this->getProviderName() . __(' service is not authenticated. Backup is still available locally.', 'wp-staging');
            return;
        }

        $this->client = $auth->getClient();
        $options = $this->auth->getOptions();
        $location = $this->auth->getLocation();
        $this->bucketName = $location[0];
        $this->path = $location[1];
        $this->maxBackupsToKeep = isset($options['maxBackupsToKeep']) ? $options['maxBackupsToKeep'] : 15;
        $this->maxBackupsToKeep = intval($this->maxBackupsToKeep);
        $this->maxBackupsToKeep = $this->maxBackupsToKeep > 0 ? $this->maxBackupsToKeep : 15;
    }

    public function setupUpload(LoggerInterface $logger, JobExportDataDto $jobDataDto, $chunkSize = 5 * 1024 * 1024)
    {
        $this->logger = $logger;
        $this->jobDataDto = $jobDataDto;
        $this->chunkSize = $chunkSize;
    }

    /**
     * @throws DiskNotWritableException
     */
    public function setBackupFilePath($backupFilePath, $fileName)
    {
        $this->fileName = $fileName;
        $this->filePath = $backupFilePath;
        $this->fileObject = new FileObject($this->filePath, FileObject::MODE_READ);

        $this->objectKey = $this->path . $this->fileName;

        // Amazon S3 support only allow 10,000 parts for a single file upload.
        // This will make sure that these parts are below 10,000 by adjusting chunkSize accordingly
        while (($this->fileObject->getSize() / 10000) > $this->chunkSize) {
            $chunkSize = 5 * 1024 * 1024;
            $this->chunkSize += $chunkSize;
        }

        $this->isObjectLocked = $this->getIsObjectLocked();
        $uploadMetadata = $this->jobDataDto->getRemoteStorageMeta();
        if (!array_key_exists($this->fileName, $uploadMetadata)) {
            $model = $this->client->createMultipartUpload([
                'Bucket' => $this->bucketName,
                'Key' => $this->objectKey,
                'ContentType' => 'application/octet-stream',
                'Metadata' => []
            ]);

            $this->setMetadata($model['UploadId'], 0, []);
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

        $partNumber = (int)ceil(($offset - 1) / $this->chunkSize);
        $partNumber++;

        $this->fileObject->fseek($offset);
        $chunk = $this->fileObject->fread($this->chunkSize);

        $parts = $fileMetadata['Parts'];
        $uploadId = $fileMetadata['UploadId'];

        $chunkSize = 0;
        try {
            $uploadParams = [
                'Bucket' => $this->bucketName,
                'Key' => $this->objectKey,
                'UploadId' => $uploadId,
                'PartNumber' => $partNumber,
                'Body' => $chunk,
            ];

            if ($this->isObjectLocked) {
                $uploadParams['ContentMD5'] = base64_encode(md5($chunk, true));
            }

            $result = $this->client->uploadPart($uploadParams);

            $parts['Parts'][$partNumber] = [
                'PartNumber' => $partNumber,
                'ETag' => $result['ETag'],
            ];

            $chunkSize = strlen($chunk);
            $offset += $chunkSize;

            if ($offset >= $this->fileObject->getSize()) {
                $result = $this->client->completeMultipartUpload([
                    'Bucket' => $this->bucketName,
                    'Key' => $this->objectKey,
                    'UploadId' => $uploadId,
                    'MultipartUpload' => $parts,
                ]);

                throw new FinishedQueueException();
            }
        } catch (FinishedQueueException $ex) {
            throw new FinishedQueueException($ex->getMessage());
        } catch (Exception $ex) {
            $result = $this->client->abortMultipartUpload([
                'Bucket' => $this->bucketName,
                'Key' => $this->objectKey,
                'UploadId' => $uploadId
            ]);

            debug_log($ex->getMessage());
            throw new StorageException("Unable to Upload to S3 Storage: " . $ex->getMessage());
        }

        $this->setMetadata($uploadId, $offset, $parts);
        return $chunkSize;
    }


    /**
     * @param string $filePath
     * @return bool
     */
    public function uploadFile($filePath)
    {
        $fileObject = new FileObject($filePath, FileObject::MODE_READ);

        $this->objectKey = $this->path . $fileObject->getBasename();

        $this->isObjectLocked = $this->getIsObjectLocked();

        $model = $this->client->createMultipartUpload([
            'Bucket' => $this->bucketName,
            'Key' => $this->objectKey,
            'ContentType' => 'application/octet-stream',
            'Metadata' => []
        ]);

        $fileObject->fseek(0);
        $chunk = $fileObject->fread($fileObject->getSize());
        $partNumber = 1;

        $uploadParams = [
            'Bucket' => $this->bucketName,
            'Key' => $this->objectKey,
            'UploadId' => $model['UploadId'],
            'PartNumber' => $partNumber,
            'Body' => $chunk,
        ];

        if ($this->isObjectLocked) {
            $uploadParams['ContentMD5'] = base64_encode(md5($chunk, true));
        }

        try {
            $result = $this->client->uploadPart($uploadParams);

            $parts['Parts'][$partNumber] = [
                'PartNumber' => $partNumber,
                'ETag' => $result['ETag'],
            ];

            $result = $this->client->completeMultipartUpload([
                'Bucket' => $this->bucketName,
                'Key' => $this->objectKey,
                'UploadId' => $model['UploadId'],
                'MultipartUpload' => $parts,
            ]);
        } catch (Exception $ex) {
            debug_log("Error: " . $ex->getMessage());

            $result = $this->client->abortMultipartUpload([
                'Bucket' => $this->bucketName,
                'Key' => $this->objectKey,
                'UploadId' => $model['UploadId']
            ]);

            return false;
        }

        return true;
    }

    public function stopUpload()
    {
        // no-op
    }

    public function getError()
    {
        return $this->error;
    }

    public function getBackups()
    {
        if ($this->client === false) {
            return [];
        }

        try {
            $objects = $this->auth->getFiles();

            // Sort by date in ascending order
            uasort($objects, function ($object1, $object2) {
                $date1 = (new \DateTime($object1['LastModified']));
                $date2 = (new \DateTime($object2['LastModified']));

                return $date1 < $date2 ? -1 : 1;
            });

            $backups = [];

            foreach ($objects as $file) {
                if ($this->strings->endsWith($file['Key'], '.wpstg') || $this->strings->endsWith($file['Key'], '.sql')) {
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
            return false;
        }

        try {
            $backupsFiles = $this->getBackups();

            if (count($backupsFiles) < $this->maxBackupsToKeep) {
                return true;
            }

            $backups = [];

            /**
             * arrange the backup in the format key value format to make it easy to delete
             * Extract the id of the backup from the file
             */
            foreach ($backupsFiles as $object) {
                $fileName = str_replace($this->path, '', $object['Key']);
                $backupId = $this->extractBackupIdFromFilename($fileName);
                if (!array_key_exists($backupId, $backups)) {
                    $backups[$backupId] = [];
                }

                if (!$this->isBackupPart($fileName)) {
                    $backups[$backupId]['id'] = $object['Key'];
                    continue;
                }

                if (!array_key_exists('parts', $backups[$backupId])) {
                    $backups[$backupId]['parts'] = [];
                }

                $backups[$backupId]['parts'][] = $object['Key'];
            }

            $backupsToDelete = count($backups) - $this->maxBackupsToKeep;
            foreach ($backups as $backup) {
                if ($backupsToDelete < 0) {
                    return true;
                }

                $result = $this->client->deleteObject([
                    'Bucket' => $this->bucketName,
                    'Key'    => $backup['id']
                ]);

                if (array_key_exists('parts', $backup)) {
                    foreach ($backup['parts'] as $part) {
                        $this->client->deleteObject([
                            'Bucket' => $this->bucketName,
                            'Key'    => $part
                        ]);
                    }
                }

                $backupsToDelete--;
            }

            return true;
        } catch (S3Exception $ex) {
            $this->error = $ex->getMessage();
            debug_log('E: ' . $this->error);
            return false;
        }
    }

    public function verifyUploads($uploadsToVerify)
    {
        $files = $this->auth->getFiles();
        $uploadsConfirmed = [];
        foreach ($files as $file) {
            $fileName = str_replace($this->path, '', $file['Key']);
            if (array_key_exists($fileName, $uploadsToVerify) && $uploadsToVerify[$fileName] === (int)$file['Size']) {
                $uploadsConfirmed[] = $fileName;
            }
        }

        return count($uploadsConfirmed) === count($uploadsToVerify);
    }

    /**
     * @param int $backupSize
     * @throws DiskNotWritableException
     */
    public function checkDiskSize($backupSize)
    {
        //no-op
    }

    protected function setMetadata($uploadId, $offset, $parts)
    {
        $this->jobDataDto->setRemoteStorageMeta([
            $this->fileName => [
                'UploadId' => $uploadId,
                'Offset' => $offset,
                'Parts' => $parts
            ]
        ]);
    }

    /** @return bool */
    protected function getIsObjectLocked()
    {
        try {
            $result = $this->client->getObjectLockConfiguration([
                'Bucket' => $this->bucketName
            ]);

            return $result['ObjectLockConfiguration']['ObjectLockEnabled'] === 'Enabled';
        } catch (Exception $ex) {
            debug_log($ex->getMessage());

            if ($this->logger !== null) {
                $this->logger->warning(__('IAM user does not have s3:GetBucketObjectLockConfiguration permission. Therefore cannot retrieve the Object Lock Configuration! Treatment as Object Lock Disabled. If upload fails extend user permission.', 'wp-staging'));
            }

            return false;
        }
    }
}
