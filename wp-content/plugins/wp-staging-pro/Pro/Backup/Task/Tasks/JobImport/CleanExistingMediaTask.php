<?php

namespace WPStaging\Pro\Backup\Task\Tasks\JobImport;

use WPStaging\Framework\Adapter\Directory;
use WPStaging\Framework\Filesystem\Filesystem;
use WPStaging\Framework\Queue\SeekableQueueInterface;
use WPStaging\Pro\Backup\Dto\StepsDto;
use WPStaging\Pro\Backup\Task\ImportTask;
use WPStaging\Vendor\Psr\Log\LoggerInterface;
use WPStaging\Framework\Utils\Cache\Cache;

class CleanExistingMediaTask extends ImportTask
{
    protected $filesystem;
    protected $directory;

    protected $processedNow;

    public function __construct(LoggerInterface $logger, Cache $cache, StepsDto $stepsDto, SeekableQueueInterface $taskQueue, Filesystem $filesystem, Directory $directory)
    {
        parent::__construct($logger, $cache, $stepsDto, $taskQueue);
        $this->filesystem = $filesystem;
        $this->directory = $directory;
    }

    public static function getTaskName()
    {
        return 'backup_restore_clean_media';
    }

    public static function getTaskTitle()
    {
        return 'Cleaning Existing Media';
    }

    public function prepareCleaningMedia()
    {
        if ($this->stepsDto->getTotal() === 0) {
            $this->taskQueue->seek(0);

            // Just an arbitrary number, when there are no more items to clean we call stepsDto->finish()
            $this->stepsDto->setTotal(100);
        }
    }

    public function execute()
    {
        if (apply_filters('wpstg.backup.restore.keepExistingMedia', false)) {
            $this->stepsDto->finish();
            $this->logger->info(esc_html__(sprintf('%s (skipped)', static::getTaskTitle()), 'wp-staging'));
            return $this->generateResponse(false);
        }

        $this->prepareCleaningMedia();

        $wpStagingUploadsDir = '/' . str_replace($this->filesystem->normalizePath(ABSPATH, true), '', $this->directory->getPluginUploadsDirectory()) . '**';

        $this->filesystem->setShouldStop([$this, 'isThreshold'])
            ->addExcludePath($wpStagingUploadsDir)
            ->setWpRootPath(ABSPATH)
            ->setRecursive();

        $result = false;
        $this->filesystem->setProcessedCount(0);

        try {
            $result = $this->filesystem->delete($this->directory->getUploadsDirectory(), false);
            $this->processedNow = $this->filesystem->getProcessedCount();
        } catch (\Exception $e) {
            //
        }

        // Finish if all media files are deleted
        if ($result !== false) {
            $this->stepsDto->finish();
        }

        $this->logger->info(esc_html__(sprintf('%s (cleaned %d items)', static::getTaskTitle(), $this->processedNow), 'wp-staging'));

        return $this->generateResponse(false);
    }
}
