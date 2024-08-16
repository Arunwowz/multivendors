<?php

namespace WPStaging\Pro\Backup\Storage;

use Exception;
use WPStaging\Framework\DI\ServiceProvider;
use WPStaging\Framework\Utils\Sanitize;
use WPStaging\Pro\Backup\BackupProcessLock;
use WPStaging\Pro\Backup\Storage\Storages\GoogleDrive\Auth as GoogleDriveStorage;
use WPStaging\Vendor\Google\Client as GoogleClient;
use WPStaging\Vendor\Google\Service\Drive as GoogleDriveService;
use WPStaging\Vendor\Google\Service\PeopleService as GooglePeopleService;
use WPStaging\Vendor\GuzzleHttp\Client as GuzzleClient;

use function WPStaging\functions\debug_log;

class StoragesServiceProvider extends ServiceProvider
{
    protected function registerClasses()
    {
        $this->setupGoogleDrive();

        $this->container->singleton(SettingsTab::class);
    }

    protected function addHooks()
    {
        add_filter('wpstg_main_settings_tabs', $this->container->callback(SettingsTab::class, 'addRemoteStoragesSettingsTab'), 10, 1);

        add_action('admin_post_wpstg-googledrive-auth', $this->container->callback(GoogleDriveStorage::class, 'authenticate'), 10, 0);
        add_action('admin_post_wpstg-googledrive-api-auth', $this->container->callback(GoogleDriveStorage::class, 'apiAuthenticate'), 10, 0);
        add_action('wp_ajax_wpstg-provider-authenticate', $this->container->callback(StorageBase::class, 'authenticate'), 10, 0);
        add_action('wp_ajax_wpstg-provider-revoke', $this->container->callback(StorageBase::class, 'revoke'), 10, 0);
        add_action('wp_ajax_wpstg-provider-settings', $this->container->callback(StorageBase::class, 'updateSettings'), 10, 0);
        add_action('wp_ajax_wpstg-provider-test-connection', $this->container->callback(StorageBase::class, 'testConnection'), 10, 0);
        add_action('all_admin_notices', $this->container->callback(GoogleDriveStorage::class, 'showAuthenticateSuccessFailureMessage'), 10, 0);
    }

    private function setupGoogleDrive()
    {
        /* $this->container->setVar('googleClientId', apply_filters('wpstg.backup.storage.googledrive.client_id',
        '425321582825-hl320nnpa8cc3sv5j9mtktjdibgac5je.apps.googleusercontent.com'));*/
        $this->container->setVar('googleClientId', apply_filters('wpstg.backup.storage.googledrive.client_id', GoogleDriveStorage::CLIENT_ID));
        $this->container->setVar('googleClientSecret', apply_filters('wpstg.backup.storage.googledrive.client_secret', ''));
        $this->container->setVar('googleRedirectURL', apply_filters('wpstg.backup.storage.googledrive.callback_url', GoogleDriveStorage::REDIRECT_URL));
        $container = $this->container;
        $this->container->bind(GoogleClient::class, function () use (&$container) {
            $googleClient = new GoogleClient();

            $config = [
                "verify" => WPSTG_PLUGIN_DIR . 'Pro/Backup/cacert.pem'
            ];

            $http = null;
            try {
                $http = new GuzzleClient($config);
                $googleClient->setHttpClient($http);
            } catch (Exception $ex) {
                debug_log($ex->getMessage());
            }

            $googleClient->setClientId($container->getVar('googleClientId'));
            if ($container->getVar('googleClientSecret') !== '') {
                $googleClient->setClientSecret($container->getVar('googleClientSecret'));
            }

            $googleClient->setRedirectUri($container->getVar('googleRedirectURL'));
            $googleClient->setScopes([GooglePeopleService::USERINFO_PROFILE, GoogleDriveService::DRIVE_FILE]);
            $googleClient->setAccessType('offline');
            return $googleClient;
        });

        $this->container->singleton(GoogleDriveStorage::class, function () use (&$container) {
            $googleClient = $container->make(GoogleClient::class);
            $backupProcessLock = $container->make(BackupProcessLock::class);
            $sanitize = $container->make(Sanitize::class);
            $googleDriveStorage =  new GoogleDriveStorage($googleClient, $backupProcessLock, $sanitize);
            if ($googleDriveStorage->isAuthenticated()) {
                $googleDriveStorage->setClientWithAuthToken();
            }

            return $googleDriveStorage;
        });

        // @todo code below doesn't work. Check if we need it when we upgrade to DI52 V3
        /*
        $this->container->bind(RemoteUploaderInterface::class, AmazonS3Uploader::class);

        $this->container->when(GoogleDriveStorageTask::class)
                        ->needs(RemoteUploaderInterface::class)
                        ->give(GoogleDriveUploader::class);

        $this->container->when(AmazonS3StorageTask::class)
                        ->needs(RemoteUploaderInterface::class)
                        ->give(AmazonS3Uploader::class);
        */
    }
}
