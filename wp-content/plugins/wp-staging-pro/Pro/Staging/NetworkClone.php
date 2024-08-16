<?php

namespace WPStaging\Pro\Staging;

use WPStaging\Backend\Pro\Notices\EntireNetworkCloneServerConfigNotice;
use WPStaging\Core\Utils\Htaccess;
use WPStaging\Framework\Facades\Sanitize;
use WPStaging\Framework\Staging\CloneOptions;

class NetworkClone
{
    /**
     * The option to check whether the clone is newly created network clone
     * Added in clone options
     */
    const NEW_NETWORK_CLONE_KEY = 'isNetworkClone';

    /**
     * The option to store base directory.
     * Required for creating .htaccess file on staging network if server is APACHE
     * Added in clone options
     */
    const NETWORK_BASE_DIR_KEY = 'networkBaseDir';

    /**
     * First time initiation of new staging network
     */
    public function init()
    {
        $cloneOptions = new CloneOptions();

        // Early bail
        if ($cloneOptions->get(self::NEW_NETWORK_CLONE_KEY) === null) {
            return;
        }

        if ($this->doesServerSupportHtaccess() && $this->creatHtaccess($cloneOptions->get(self::NETWORK_BASE_DIR_KEY))) {
            $cloneOptions->delete(self::NEW_NETWORK_CLONE_KEY);
            return;
        }

        (new EntireNetworkCloneServerConfigNotice())->enable();
        $cloneOptions->delete(self::NEW_NETWORK_CLONE_KEY);
    }

    /**
     * Check if the current server support htaccess file configurations
     *
     * @return boolean
     */
    public function doesServerSupportHtaccess()
    {
        $serverSoftware = isset($_SERVER['SERVER_SOFTWARE']) ? Sanitize::sanitizeString($_SERVER['SERVER_SOFTWARE']) : '';
        // Current Server is apache. Htaccess file supported
        if (stripos($serverSoftware, 'apache') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Return true if htaccess file added.
     * Return null if htaccess file already existed
     * Else return false
     *
     * @return null|boolean
     */
    protected function creatHtaccess($baseDir)
    {
        $htaccessPath = trailingslashit(ABSPATH) . '.htaccess';
        // Early bail if file already exists
        if (file_exists($htaccessPath)) {
            return null;
        }

        return (new Htaccess())->createForStagingNetwork($htaccessPath, $baseDir);
    }
}
