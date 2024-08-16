<?php

namespace WPStaging\Pro\Notices;

use WPStaging\Backend\Pro\Notices\BackupsDifferentPrefixNotice;
use WPStaging\Backend\Pro\Notices\EntireNetworkCloneServerConfigNotice;

/**
 * Dismiss notice depending upon post request
 */
class DismissNotice
{
    /**
     * @var BackupsDifferentPrefixNotice
     */
    private $backupsPrefixNotice;

    /**
     * @var EntireNetworkCloneServerConfigNotice
     */
    private $networkCloneNotice;

    public function __construct(BackupsDifferentPrefixNotice $backupsPrefixNotice, EntireNetworkCloneServerConfigNotice $networkCloneNotice)
    {
        $this->backupsPrefixNotice = $backupsPrefixNotice;
        $this->networkCloneNotice  = $networkCloneNotice;
    }

    /**
     * Dismiss Pro Notice
     *
     * @param string $noticeToDismiss
     */
    public function dismiss($noticeToDismiss)
    {
        if ($noticeToDismiss === 'backups_diff_prefix' && $this->backupsPrefixNotice->disable() !== false) {
            wp_send_json(true);
            return;
        }

        if ($noticeToDismiss === 'entire_clone_server_config' && $this->networkCloneNotice->disable() !== false) {
            wp_send_json(true);
            return;
        }

        wp_send_json(null);
    }
}
