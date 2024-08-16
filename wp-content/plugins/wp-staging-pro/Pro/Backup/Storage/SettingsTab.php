<?php

namespace WPStaging\Pro\Backup\Storage;

class SettingsTab
{
    /**
     * Add remote storage tab
     *
     * @filter wpstg_main_settings_tabs
     * @retrun tabs in key and title format
     */
    public function addRemoteStoragesSettingsTab($tabs)
    {
        $tabs['remote-storages'] = __("Remote Storage Providers", "wp-staging");

        return $tabs;
    }
}
