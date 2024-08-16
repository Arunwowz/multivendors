<?php

namespace WPStaging\Backend\Pro\Modules\Jobs;

use Exception;
use WPStaging\Backend\Modules\Jobs\JobExecutable;
use WPStaging\Backend\Pro\Modules\Jobs\Copiers\Copier;
use WPStaging\Core\WPStaging;
use WPStaging\Framework\Filesystem\Filesystem;
use WPStaging\Framework\Traits\FileScanToCacheTrait;
use WPStaging\Framework\Utils\WpDefaultDirectories;

/**
 * Class ScanDirectories
 * Scan the file system for all files and folders to copy
 * @package WPStaging\Backend\Modules\Directories
 *
 * @todo This class need more code DRY
 */
class ScanDirectories extends JobExecutable
{
    use FileScanToCacheTrait;

    /**
     * @var array
     */
    private $files = [];

    /**
     * Total steps to do
     * @var int
     */
    private $total = 4;

    /**
     * File name of the caching file
     * @var string
     */
    private $filename;

    /**
     * @var WpDefaultDirectories
     */
    private $wpDirectories;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $stagingPath;

    /**
     * @var string
     */
    private $stagingWpContentPath;

    /**
     * Initialize
     */
    public function initialize()
    {
        $this->filename = $this->cache->getCacheDir() . "files_to_copy." . $this->cache->getCacheExtension();
        $this->wpDirectories = new WpDefaultDirectories();
        /** @var Filesystem */
        $this->filesystem = WPStaging::make(Filesystem::class);
        $this->stagingPath = rtrim($this->filesystem->normalizePath($this->options->path));

        $this->stagingWpContentPath = $this->stagingPath . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR;
    }

    /**
     * Calculate Total Steps in This Job and Assign It to $this->options->totalSteps
     * @return void
     */
    protected function calculateTotalSteps()
    {

        $this->options->totalSteps = $this->total + count($this->options->extraDirectories);
    }

    /**
     * Start Module
     * @return object
     */
    public function start()
    {

        // Execute steps
        $this->run();

        // Save option, progress
        $this->saveProgress();

        return (object) $this->response;
    }

    /**
     * Step 0
     * Get Plugin Files
     */
    public function getStagingPlugins()
    {
        $path = $this->stagingWpContentPath . 'plugins' . DIRECTORY_SEPARATOR;
        $normalizePath = $this->filesystem->normalizePath($path);

        if ($this->isDirectoryExcluded($path)) {
            $this->log('Scanning: Skip ' . $normalizePath);
            return true;
        }

        $files = $this->open($this->filename, 'a');

        $excludeFolders = [
            '**/nbproject',
            '**/wp-staging*', // remove all wp-staging plugins
            '**/wps-hide-login',
            '**/' . Copier::PREFIX_BACKUP . '*',
            '**/' . Copier::PREFIX_TEMP . '*',
        ];

        $this->log(sprintf('Scanning %s for its sub-directories and files', $normalizePath));
        try {
            $this->options->totalFiles += $this->scanToCacheFile($files, $path, true, $excludeFolders, [], $this->stagingPath);
        } catch (Exception $e) {
            $this->returnException('Error: ' . $e->getMessage());
        }

        $this->close($files);
        return true;
    }

    /**
     * Step 1
     * Get Themes Files
     */
    public function getStagingThemes()
    {
        $path = $this->stagingWpContentPath . 'themes' . DIRECTORY_SEPARATOR;
        $normalizePath = $this->filesystem->normalizePath($path);

        if ($this->isDirectoryExcluded($path)) {
            $this->log('Scanning: Skip ' . $normalizePath);
            return true;
        }

        $files = $this->open($this->filename, 'a');

        $excludeFolders = [
            '**/nbproject',
            '**/' . Copier::PREFIX_BACKUP . '*',
            '**/' . Copier::PREFIX_TEMP . '*',
        ];

        $this->log(sprintf('Scanning %s for its sub-directories and files', $normalizePath));
        try {
            $this->options->totalFiles += $this->scanToCacheFile($files, $path, true, $excludeFolders, [], $this->stagingPath);
        } catch (Exception $e) {
            $this->returnException('Error: ' . $e->getMessage());
        }

        $this->close($files);
        return true;
    }

    /**
     * Step 2
     * Get Media Files
     */
    public function getStagingUploads()
    {
        if ($this->isMultisiteAndPro()) {
            // Detect the method, old or new one. Older WP Staging sites used a fixed upload location wp-content/uploads/2019 where the new approach keep the multisites
            $folder = $this->stagingWpContentPath . 'uploads' . DIRECTORY_SEPARATOR . 'sites';
            if (is_dir($folder)) {
                $this->getStagingUploadsNew();
            } else {
                $this->getStagingUploadsOld();
            }

            return true;
        }

        $path = $this->stagingPath . DIRECTORY_SEPARATOR . $this->getUploadFolder() . DIRECTORY_SEPARATOR;
        $normalizePath = $this->filesystem->normalizePath($path);

        // Skip it
        if ($this->isDirectoryExcluded($normalizePath)) {
            $this->log('Scanning: Skip ' . $normalizePath);
            return true;
        }

        if (!is_dir($normalizePath)) {
            $this->log('Scanning: Not a valid path ' . $normalizePath);
            return true;
        }

        $files = $this->open($this->filename, 'a');

        $relpath = str_replace($this->stagingPath, '', $normalizePath);

        $excludeFolders = [
            '**/node_modules',
            '**/nbproject',
            $relpath . 'wp-staging',
        ];

        $this->log(sprintf('Scanning %s for its sub-directories and files', $normalizePath));

        try {
            $this->options->totalFiles += $this->scanToCacheFile($files, $path, true, $excludeFolders, [], $this->stagingPath);
        } catch (Exception $e) {
            $this->returnException('Error: ' . $e->getMessage());
        }

        $this->close($files);
        return true;
    }

     /**
     * Get all files from the upload folder. Old approach 2018
     * This is used only for compatibility reasons and will be removed in the future
     * @deprecated since version 2.7.6
     * @return boolean
     */
    public function getStagingUploadsOld()
    {
        $path = $this->stagingWpContentPath . 'uploads' . DIRECTORY_SEPARATOR;
        $normalizePath = $this->filesystem->normalizePath($path);

        if ($this->isDirectoryExcluded($path)) {
            return true;
        }

        if (!is_dir($path)) {
            return true;
        }

        $files = $this->open($this->filename, 'a');

        $excludeFolders = [
            '**/wp-staging',
            '**/node_modules',
            '**/nbproject',
        ];

        $this->log(sprintf('Scanning %s for its sub-directories and files', $normalizePath));

        try {
            $this->options->totalFiles += $this->scanToCacheFile($files, $path, true, $excludeFolders, [], $this->stagingPath);
        } catch (Exception $e) {
            $this->returnException('Error: ' . $e->getMessage());
        }

        $this->close($files);
        return true;
    }

    /**
     * Get all files from the upload folder. New approach 2019
     * @return boolean
     */
    private function getStagingUploadsNew()
    {
        $path = trailingslashit($this->stagingPath) . $this->getRelUploadDir();
        $normalizePath = $this->filesystem->normalizePath($path);

        if ($this->isDirectoryExcluded($path)) {
            return true;
        }

        if (!is_dir($path)) {
            return true;
        }

        $files = $this->open($this->filename, 'a');

        $excludeFolders = [
            '**/wp-staging',
            '**/node_modules',
            '**/nbproject',
        ];

        $this->log(sprintf('Scanning %s for its sub-directories and files', $normalizePath));

        try {
            $this->options->totalFiles += $this->scanToCacheFile($files, $path, true, $excludeFolders, [], $this->stagingPath);
        } catch (Exception $e) {
            $this->returnException('Error: ' . $e->getMessage());
        }

        $this->close($files);
        return true;
    }

    /**
     * Get relative path to upload dir of staging site e.g. /wp-content/uploads/sites/2 for childsites or wp-content/uploads for main network site
     */
    private function getRelUploadDir()
    {
        $uploads     = wp_upload_dir();
        $basedir     = $uploads['basedir'];
        $relativeDir = str_replace(ABSPATH, '', $this->filesystem->normalizePath($basedir, true));
        return trailingslashit($relativeDir);
    }

    /**
     * Get the relative path to uploads folder of the multisite live site e.g. wp-content/blogs.dir/ID/files or wp-content/upload/sites/ID or wp-content/uploads
     * @param string $pathUploadsFolder Absolute path to the uploads folder
     * @param string $subPathName
     * @return string
     */
    private function getRelUploadPath()
    {
        // Check first which structure is used
        $uploads = wp_upload_dir();
        $basedir = $uploads['basedir'];
        $blogId  = get_current_blog_id();
        if (strpos($basedir, 'blogs.dir') === false) {
            // Since WP 3.5
            $getRelUploadPath = $blogId > 1 ?
                    'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . get_current_blog_id() . DIRECTORY_SEPARATOR :
                    'wp-content' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        } else {
            // old blog structure before WP 3.5
            $getRelUploadPath = $blogId > 1 ?
                    'wp-content' . DIRECTORY_SEPARATOR . 'blogs.dir' . DIRECTORY_SEPARATOR . get_current_blog_id() . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR :
                    'wp-content' . DIRECTORY_SEPARATOR;
        }
        return $getRelUploadPath;
    }

    /**
     * Step 4 - x
     * Get extra folders of the wp root level
     * Does not collect wp-includes, wp-admin and wp-content folder
     */
    private function getExtraFiles($folder)
    {
        $folder = rtrim($folder, DIRECTORY_SEPARATOR);
        $normalizePath = $this->filesystem->normalizePath($folder);

        if (!is_dir($normalizePath)) {
            return true;
        }

        $files = $this->open($this->filename, 'a');

        $this->log(sprintf('Scanning %s for its sub-directories and files', $normalizePath));

        try {
            $this->options->totalFiles += $this->scanToCacheFile($files, $normalizePath, true, [], [], $this->stagingPath);
        } catch (Exception $e) {
            $this->returnException('Error: ' . $e->getMessage());
        }

        $this->close($files);
        return true;
    }

    /**
     * Closes a file handle
     *
     * @param  resource $handle File handle to close
     * @return boolean
     */
    public function close($handle)
    {
        return @fclose($handle);
    }

    /**
     * Opens a file in specified mode
     *
     * @param  string   $file Path to the file to open
     * @param  string   $mode Mode in which to open the file
     * @return resource
     * @throws Exception
     */
    public function open($file, $mode)
    {

        $file_handle = @fopen($file, $mode);
        if ($file_handle === false) {
            $this->returnException(sprintf(__('Unable to open %s with mode %s', 'wp-staging'), $file, $mode));
        }

        return $file_handle;
    }

    /**
     * Write contents to a file
     *
     * @param  resource $handle  File handle to write to
     * @param  string   $content Contents to write to the file
     * @return integer
     * @throws Exception
     * @throws Exception
     */
    public function write($handle, $content)
    {
        $write_result = @fwrite($handle, $content);
        if ($write_result === false) {
            if (( $meta = stream_get_meta_data($handle) )) {
                //$this->returnException(sprintf(__('Unable to write to: %s', 'wp-staging'), $meta['uri']));
                throw new Exception(sprintf(__('Unable to write to: %s', 'wp-staging'), $meta['uri']));
            }
        } elseif ($write_result !== strlen($content)) {
            //$this->returnException(__('Out of disk space.', 'wp-staging'));
            throw new Exception(__('Out of disk space.', 'wp-staging'));
        }

        return $write_result;
    }

    /**
     * Execute the Current Step
     * Returns false when over threshold limits are hit or when the job is done, true otherwise
     * @return bool
     */
    protected function execute()
    {

        // No job left to execute
        if ($this->isFinished()) {
            $this->prepareResponse(true, false);
            return false;
        }


//        if ($this->options->currentStep == 0) {
//            $this->getStagingWpRootFiles();
//            $this->prepareResponse(false, true);
//            return false;
//        }

        if ($this->options->currentStep == 0) {
            $this->getStagingPlugins();
            $this->prepareResponse(false, true);
            return false;
        }
        if ($this->options->currentStep == 1) {
            $this->getStagingThemes();
            $this->prepareResponse(false, true);
            return false;
        }
        if ($this->options->currentStep == 2) {
            $this->getStagingUploads();
            $this->prepareResponse(false, true);
            return false;
        }

        if (isset($this->options->extraDirectories[$this->options->currentStep - $this->total])) {
            $this->getExtraFiles($this->options->extraDirectories[$this->options->currentStep - $this->total]);
            $this->prepareResponse(false, true);
            return false;
        }

//        if ($this->options->currentStep == 3) {
//            $this->getStagingWpAdminFiles();
//            $this->prepareResponse(false, true);
//            return false;
//        }
        // Not finished - Prepare response
        $this->prepareResponse(false, true);
        return true;
    }

    /**
     * Checks Whether There is Any Job to Execute or Not
     * @return bool
     */
    protected function isFinished()
    {
        return ($this->options->currentStep > $this->options->totalSteps);
    }

    /**
     * Save files
     * @return bool
     */
    protected function saveProgress()
    {
        return $this->saveOptions();
    }

    /**
     * Get files
     * @return void
     */
    protected function getFiles()
    {
        $fileName = $this->cache->getCacheDir() . "files_to_copy." . $this->cache->getCacheExtension();

        if (($this->files = @file_get_contents($fileName)) === false) {
            $this->files = [];
            return;
        }

        $this->files = explode(PHP_EOL, $this->files);
    }

    /**
     * Replace forward slash with current directory separator
     * Windows Compatibility Fix
     * @param string $path Path
     *
     * @return string
     */
    private function sanitizeDirectorySeparator($path)
    {
        $string = str_replace("/", "\\", $path);
        return str_replace('\\\\', '\\', $string);
    }

    /**
     * Check if directory is excluded from scanning
     * @param string $directory
     * @return bool
     */
    protected function isDirectoryExcluded($directory)
    {
        $directory = $this->sanitizeDirectorySeparator($directory);
        foreach ($this->options->excludedDirectories as $excludedDirectory) {
            $excludedDirectory = $this->sanitizeDirectorySeparator($excludedDirectory);
            if (strpos(trailingslashit($directory), trailingslashit($excludedDirectory)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get WP media folder
     *
     * @return string
     */
    protected function getUploadFolder()
    {
        $uploads = wp_upload_dir();
        $folder  = str_replace(ABSPATH, '', $uploads['basedir']);
        return $folder;
    }
}
