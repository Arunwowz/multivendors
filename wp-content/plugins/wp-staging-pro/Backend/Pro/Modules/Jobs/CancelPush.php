<?php

namespace WPStaging\Backend\Pro\Modules\Jobs;

use WPStaging\Core\WPStaging;
use WPStaging\Backend\Pro\Modules\Jobs\DatabaseTmp;
use WPStaging\Backend\Pro\Modules\Jobs\Copiers\PluginsCopier;
use WPStaging\Backend\Pro\Modules\Jobs\Copiers\ThemesCopier;
use WPStaging\Framework\Adapter\Directory;

/**
 * Class Cancel Pushing Process
 * @package WPStaging\Backend\Modules\Jobs
 */
class CancelPush
{

    /**
     * directory
     *
     * @var Directory
     */
    private $directory;

    /**
     * pluginsCopier
     *
     * @var mixed
     */
    private $pluginsCopier;

    /**
     * themesCopier
     *
     * @var mixed
     */
    private $themesCopier;

    public function __construct(Directory $directory, PluginsCopier $pluginsCopier, ThemesCopier $themesCopier)
    {
        $this->directory = $directory;
        $this->pluginsCopier = $pluginsCopier;
        $this->themesCopier  = $themesCopier;
    }
    /**
     * Cancel push
     * @return void
     */
    public function start()
    {
        $this->cleanUpTables();
        $this->cleanUpFiles();

        return;
    }

    /**
     * Clean up db temp tables
     *
     * @return void
     */
    protected function cleanUpTables()
    {
        $db = WPStaging::make('wpdb');
        $tablesList = $db->get_results(
            $db->prepare('SHOW TABLES  like "%%%s%%";', $db->esc_like(DatabaseTmp::TMP_PREFIX))
        );
        foreach ($tablesList as $tableObj) {
            foreach ($tableObj as $tableName) {
                $db->query("DROP TABLE IF EXISTS `$tableName`");
            }
        }
    }

    /**
     * Clean up temp files(plugins, themes, cache)
     *
     * @return void
     */
    protected function cleanUpFiles()
    {
        $this->deleteFiles($this->glob(trailingslashit($this->directory->getPluginUploadsDirectory()), "*.cache"));
        $this->deleteFiles($this->glob(trailingslashit($this->directory->getCacheDirectory()), "*.cache"));
        $this->deleteFiles($this->glob(trailingslashit($this->directory->getCacheDirectory()), "*.sql"));
        $this->pluginsCopier->cleanup();
        $this->themesCopier->cleanup();
    }

    /**
     * Delete files
     *
     * @param  array $files list of files to delete
     * @return void
     */
    protected function deleteFiles($files)
    {
        array_map(function ($fileName) {
            return $this->directory->getFileSystem()->delete($fileName);
        }, $files);
    }

    /**
     * Glob that is safe with streams (vfs for example)
     *
     * @param string $directory
     * @param string $filePattern
     * @return array
     */
    private function glob($directory, $filePattern)
    {
        $files = scandir($directory);
        $found = [];
        foreach ($files as $filename) {
            if (fnmatch($filePattern, $filename)) {
                $found[] = $directory . '/' . $filename;
            }
        }

        return $found;
    }
}
