<?php

// TODO PHP7.x; declare(strict_types=1);
// TODO PHP7.x; return types && type-hints
// TODO PHP7.1; constant visibility

namespace WPStaging\Pro\Backup\Service;

use RuntimeException;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Adapter\Directory;
use WPStaging\Framework\Adapter\PhpAdapter;
use WPStaging\Framework\Filesystem\PathIdentifier;
use WPStaging\Pro\Backup\Dto\JobDataDto;
use WPStaging\Pro\Backup\Dto\Service\CompressorDto;
use WPStaging\Framework\Utils\Cache\BufferedCache;
use WPStaging\Pro\Backup\Entity\MultipartMetadata;
use WPStaging\Pro\Backup\Exceptions\DiskNotWritableException;

class Compressor
{
    const BACKUP_DIR_NAME = 'backups';

    /** @var BufferedCache */
    private $tempBackupIndex;

    /** @var BufferedCache */
    private $tempBackup;

    /** @var CompressorDto */
    private $compressorDto;

    /** @var PathIdentifier */
    private $pathIdentifier;

    /** @var int */
    private $compressedFileSize = 0;

    /** @var JobDataDto */
    private $jobDataDto;

    /** @var PhpAdapter */
    private $phpAdapter;

    /**
     * Category can be an empty string, plugins, mu-plugins, themes, uploads, other or database
     * Where empty is for single file backup,
     * BackupFinder::INDEX_IDENTIFIER is for splitted backup index file
     * And other is for files from wp-content not including plugins, mu-plugins, themes, uploads
     * @var string
     */
    private $category = '';

    /**
     * The current index of category in which appending files
     * @var int
     */
    private $categoryIndex = 0;

    /** @var bool */
    private $isLocalBackup = false;

    protected $bytesWrittenInThisRequest = 0;

    // TODO telescoped
    public function __construct(BufferedCache $cacheIndex, BufferedCache $tempBackup, PathIdentifier $pathIdentifier, JobDataDto $jobDataDto, CompressorDto $compressorDto, PhpAdapter $phpAdapter)
    {
        $this->jobDataDto = $jobDataDto;
        $this->compressorDto = $compressorDto;
        $this->tempBackupIndex = $cacheIndex;
        $this->tempBackup = $tempBackup;
        $this->pathIdentifier = $pathIdentifier;
        $this->phpAdapter = $phpAdapter;

        $this->setCategory('');
    }

    public function setCategoryIndex($index)
    {
        if (empty($index)) {
            $index = 0;
        }

        $this->categoryIndex = $index;
        $this->setCategory($this->category, true);
    }

    public function setCategory($category = '', $create = false)
    {
        $this->category = $category;
        $this->setupTmpBackup();

        if ($create && !$this->tempBackup->isValid()) {
            // Create temp file with binary header
            $this->tempBackup->save(file_get_contents(WPSTG_PLUGIN_DIR . 'Pro/Backup/wpstgBackupHeader.txt'));
        }
    }

    public function setupTmpBackup()
    {
        $additionalInfo = $this->category === 'main' || $this->category === '' ? '' : $this->category . '_' . $this->categoryIndex . '_';

        $this->tempBackup->setFilename('temp_wpstg_backup_' . $additionalInfo . $this->jobDataDto->getId());
        $this->tempBackup->setLifetime(DAY_IN_SECONDS);

        $this->tempBackupIndex->setFilename('temp_backup_index_' . $additionalInfo . $this->jobDataDto->getId());
        $this->tempBackupIndex->setLifetime(DAY_IN_SECONDS);
    }

    public function doExceedMaxPartSize($fileSize, $maxPartSize)
    {
        $allowedSize = $fileSize - $this->compressorDto->getWrittenBytesTotal();
        $sizeAfterAdding = $allowedSize + filesize($this->tempBackup->getFilePath());
        return $sizeAfterAdding >= $maxPartSize;
    }

    /**
     * @var bool $isLocalBackup
     */
    public function setIsLocalBackup($isLocalBackup)
    {
        $this->isLocalBackup = $isLocalBackup;
    }

    /**
     * @return CompressorDto
     */
    public function getDto()
    {
        return $this->compressorDto;
    }

    public function getBytesWrittenInThisRequest()
    {
        return $this->bytesWrittenInThisRequest;
    }

    /**
     * @param string $fullFilePath
     *
     * `true` -> finished
     * `false` -> not finished
     * `null` -> skip / didn't do anything
     *
     * @throws DiskNotWritableException
     * @throws RuntimeException
     *
     * @return bool|null
     */
    public function appendFileToBackup($fullFilePath)
    {
        // We can use evil '@' as we don't check is_file || file_exists to speed things up.
        // Since in this case speed > anything else
        // However if @ is not used, depending on if file exists or not this can throw E_WARNING.
        $resource = @fopen($fullFilePath, 'rb');
        if (!$resource) {
            return null;
        }

        $fileStats = fstat($resource);
        $this->initiateDtoByFilePath($fullFilePath, $fileStats);
        $writtenBytesBefore = $this->compressorDto->getWrittenBytesTotal();
        $writtenBytesTotal = $this->appendToCompressedFile($resource, $fullFilePath);
        $this->addIndex($writtenBytesTotal);
        $this->compressorDto->setWrittenBytesTotal($writtenBytesTotal);

        $this->bytesWrittenInThisRequest += $writtenBytesTotal - $writtenBytesBefore;

        $isFinished = $this->compressorDto->isFinished();

        $this->compressorDto->resetIfFinished();

        return $isFinished;
    }

    public function initiateDtoByFilePath($filePath, array $fileStats = [])
    {
        if ($filePath === $this->compressorDto->getFilePath() && $fileStats['size'] === $this->compressorDto->getFileSize()) {
            return;
        }

        $this->compressorDto->setFilePath($filePath);
        $this->compressorDto->setFileSize($fileStats['size']);
    }

    /**
     * @param string $category
     * @param string $partName
     * @param string $categoryIndex
     */
    public function setBackupMetadataForBackupPart($category, $partName, $categoryIndex)
    {
        $this->category = $category;
        $this->categoryIndex = $categoryIndex;
        $this->setupTmpBackup();
        $this->generateBackupMetadata($partName, $isBackupPart = true);
    }

    /**
     * Combines index and compressed file, renames / moves it to destination
     *
     * This function is called only once, so performance improvements has no impact here.
     *
     * @param string $finalFileNameOnRename
     * @param bool $isBackupPart
     *
     * @return string|null
     */
    public function generateBackupMetadata($finalFileNameOnRename = '', $isBackupPart = false)
    {
        clearstatcache();
        $backupSizeBeforeAddingIndex = filesize($this->tempBackup->getFilePath());

        $backupSizeBeforeAddingIndex = $this->addFileIndex();

        clearstatcache();
        $backupSizeAfterAddingIndex = filesize($this->tempBackup->getFilePath());

        $backupMetadata = $this->compressorDto->getBackupMetadata();
        $backupMetadata->setHeaderStart($backupSizeBeforeAddingIndex);
        $backupMetadata->setHeaderEnd($backupSizeAfterAddingIndex);

        if ($isBackupPart) {
            $splitMetadata = $backupMetadata->getMultipartMetadata();
            $splitMetadata = empty($splitMetadata) ? new MultipartMetadata() : $splitMetadata;
            $splitMetadata->setTotalFiles($this->jobDataDto->getFilesInPart($this->category, $this->categoryIndex));
            $backupMetadata->setMultipartMetadata($splitMetadata);
        }

        $this->tempBackup->append(json_encode($backupMetadata));

        return $this->renameExport($finalFileNameOnRename);
    }

    public function getMoveActionInfo()
    {
        return [
            $this->tempBackup->getFilePath(),
            $this->getDestinationPath()
        ];
    }

    /** @return int */
    private function addFileIndex()
    {
        clearstatcache();
        $indexResource = fopen($this->tempBackupIndex->getFilePath(), 'rb');

        if (!$indexResource) {
            throw new RuntimeException('Index file not found!');
        }

        $lastLine = $this->tempBackup->readLastLine();
        if ($lastLine !== PHP_EOL) {
            // See if this is really needed, removing an extra empty line should not impact backup performance
            if (empty($lastLine)) {
                $this->tempBackup->deleteBottomBytes(strlen(PHP_EOL));
            }

            $this->tempBackup->append(PHP_EOL);
        }

        $indexStats = fstat($indexResource);
        $this->initiateDtoByFilePath($this->tempBackupIndex->getFilePath(), $indexStats);

        clearstatcache();
        $backupSizeBeforeAddingIndex = filesize($this->tempBackup->getFilePath());

        // Write the index to the backup file, regardless of resource limits threshold
        $writtenBytes = $this->appendToCompressedFile($indexResource, $this->tempBackupIndex->getFilePath());
        $this->compressorDto->appendWrittenBytes($writtenBytes);

        // close the index file handle to make it deleteable for Windows where PHP < 7.3
        fclose($indexResource);
        $this->tempBackupIndex->delete();
        $this->compressorDto->resetIfFinished();

        $this->tempBackup->append(PHP_EOL);

        return $backupSizeBeforeAddingIndex;
    }

    private function getDestinationPath()
    {
        $extension = "wpstg";

        if ($this->category !== '') {
            $index = $this->categoryIndex === 0 ? '' : ($this->categoryIndex . '.');
            $extension = $this->category . '.' . $index . $extension;
        }

        return sprintf(
            '%s_%s_%s.%s',
            parse_url(get_home_url())['host'],
            current_time('Ymd-His'),
            $this->jobDataDto->getId(),
            $extension
        );
    }

    public function getFinalPath($renameFileTo = '', $isLocalBackup = true)
    {
        $backupsDirectory = '';
        if ($isLocalBackup) {
            $backupsDirectory = WPStaging::make(BackupsFinder::class)->getBackupsDirectory();
        } else {
            $backupsDirectory = WPStaging::make(Directory::class)->getCacheDirectory();
        }

        if ($renameFileTo === '') {
            $renameFileTo = $this->getDestinationPath();
        }

        return $backupsDirectory . $renameFileTo;
    }

    /** @var string $renameFileTo */
    private function renameExport($renameFileTo = '')
    {
        if ($renameFileTo === '') {
            $renameFileTo = $this->getDestinationPath();
        }

        $destination = trailingslashit(dirname($this->tempBackup->getFilePath())) . $renameFileTo;
        if ($this->isLocalBackup) {
            $destination = $this->getFinalPath($renameFileTo);
        }

        if (!rename($this->tempBackup->getFilePath(), $destination)) {
            throw new RuntimeException('Failed to generate destination');
        }

        return $destination;
    }

    /**
     * @param int $writtenBytesTotal
     */
    private function addIndex($writtenBytesTotal)
    {
        clearstatcache();
        if (file_exists($this->tempBackup->getFilePath())) {
            $this->compressedFileSize = filesize($this->tempBackup->getFilePath());
        }

        $start = max($this->compressedFileSize - $writtenBytesTotal, 0);

        if (!$this->compressorDto->isIndexPositionCreated($this->category, $this->categoryIndex)) {
            $identifiablePath = $this->pathIdentifier->transformPathToIdentifiable($this->compressorDto->getFilePath());
            $info = $identifiablePath . '|' . $start . ':' . $writtenBytesTotal;
            $this->tempBackupIndex->append($info);
            $this->compressorDto->setIndexPositionCreated(true);

            /*
             * We require JobDataDto in the constructor because it is wired in the DI container
             * to the current job DTO instance. However, here we need to make sure this DTO
             * is the jobExportDataDto.
             */
            if (!$this->phpAdapter->isCallable([$this->jobDataDto, 'setTotalFiles']) || !$this->phpAdapter->isCallable([$this->jobDataDto, 'getTotalFiles'])) {
                throw new \LogicException('This method can only be called from the context of Export');
            }

            $this->jobDataDto->setTotalFiles($this->jobDataDto->getTotalFiles() + 1);

            if ($this->jobDataDto->getIsMultipartBackup()) {
                $filesCount = $this->jobDataDto->getFilesInPart($this->category, $this->categoryIndex);
                $this->jobDataDto->setFilesInPart($this->category, $this->categoryIndex, $filesCount + 1);
            }

            return;
        }

        $lastLine = $this->tempBackupIndex->readLines(1, null, BufferedCache::POSITION_BOTTOM);
        if (!is_array($lastLine)) {
            throw new RuntimeException('Failed to read backup metadata file index information. Error: The last line is no array.');
        }

        $lastLine = array_filter($lastLine, function ($item) {
            return !empty($item) && strpos($item, ':') !== false && strpos($item, '|') !== false;
        });

        if (count($lastLine) !== 1) {
            throw new RuntimeException('Failed to read backup metadata file index information. Error: The last line is not an array or element with countable interface.');
        }

        $lastLine = array_shift($lastLine);

        list($relativePath, $indexPosition) = explode('|', trim($lastLine));

        // ['9378469', '4491']
        list($offsetStart, $writtenPreviously) = explode(':', trim($indexPosition));

        // @todo Should we use mb_strlen($_writtenBytes, '8bit') instead of strlen?
        $this->tempBackupIndex->deleteBottomBytes(strlen($lastLine));

        $identifiablePath = $this->pathIdentifier->transformPathToIdentifiable($this->compressorDto->getFilePath());
        $info = $identifiablePath . '|' . $offsetStart . ':' . $writtenBytesTotal;
        $this->tempBackupIndex->append($info);
        $this->compressorDto->setIndexPositionCreated(true, $this->category, $this->categoryIndex);
    }

    /**
     * @param $resource
     * @param $filePath
     *
     * @return int
     * @throws DiskNotWritableException
     * @throws RuntimeException
     */
    private function appendToCompressedFile($resource, $filePath)
    {
        try {
            return $this->tempBackup->appendFile(
                $resource,
                $this->compressorDto->getWrittenBytesTotal()
            );
        } catch (DiskNotWritableException $e) {
            // Re-throw for readability
            throw $e;
        }
    }
}
