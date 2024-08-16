<?php

namespace WPStaging\Pro\Backup\Storage\Storages\GoogleDrive;

use Exception;
use InvalidArgumentException;
use WPStaging\Framework\Utils\Math;
use WPStaging\Framework\Utils\Sanitize;
use WPStaging\Pro\Backup\BackupProcessLock;
use WPStaging\Pro\Backup\Exceptions\ProcessLockedException;
use WPStaging\Pro\Backup\Storage\AbstractStorage;
use WPStaging\Vendor\Google\Client as GoogleClient;
use WPStaging\Vendor\Google\Service\Drive as GoogleDriveService;
use WPStaging\Vendor\Google\Service\Drive\DriveFile as GoogleDriveFile;
use WPStaging\Vendor\GuzzleHttp\Client as GuzzleClient;
use WPStaging\Vendor\GuzzleHttp\Exception\ClientException;
use WPStaging\Vendor\GuzzleHttp\Exception\RequestException;
use WPStaging\Vendor\Google\Service\Drive\FileList;
use WPStaging\Vendor\GuzzleHttp\Exception\ConnectException;

use function WPStaging\functions\debug_log;

class Auth extends AbstractStorage
{
    /** @var string */
    const REDIRECT_URL = 'https://auth.wp-staging.com/googledrive';

    /** @var string */
    const REFRESH_URL  = 'https://auth.wp-staging.com/googledrive/refresh';

    /** @var string */
    const CLIENT_ID    = '742905498798-io5jrk3au4fi1qeu9u3c3krbro97ofl1.apps.googleusercontent.com';

    /** @var string */
    const FOLDER_NAME  = 'WP STAGING Backups';

    /** @var GoogleClient */
    private $client;

    /** @var BackupProcessLock */
    private $backupProcessLock;

    /** @var Sanitize */
    protected $sanitize;

    public function __construct(GoogleClient $client, BackupProcessLock $backupProcessLock, Sanitize $sanitize)
    {
        $this->identifier = 'googledrive';
        $this->label = 'Google Drive';
        $this->client = $client;
        $this->backupProcessLock = $backupProcessLock;
        $this->maybeOverrideClientConfig();
        $this->sanitize = $sanitize;
    }

    /**
     * Get Google Authorization URL
     *
     * @return string Google Authentication URL
     */
    public function getAuthenticationURL()
    {
        $this->client->setApprovalPrompt('force');
        $state = add_query_arg(
            [
                'tab' => 'remote-storages',
                'action' => 'wpstg-googledrive-auth',
                'sub' => 'googledrive'
            ],
            admin_url('admin-post.php')
        );
        $this->client->setState($state);

        try {
            return $this->client->createAuthUrl();
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * @return void
     */
    public function testConnection()
    {
        // no-op
    }

    /*
     * Authentication of the storage
     * @return void
     */
    public function authenticate()
    {
        $options = $this->getOptions();
        $options = array_merge($options, [
            'isAuthenticated' => true,
            'refreshToken' => isset($_GET['refresh-token']) ? $this->sanitize->decodeBase64AndSanitize($_GET['refresh-token']) : '',
            'accessToken' => isset($_GET['access-token']) ? $this->sanitize->decodeBase64AndSanitize($_GET['access-token']) : '',
            'expiresIn' => isset($_GET['expires-in']) ? $this->sanitize->sanitizeInt($_GET['expires-in']) : 0,
            'created' =>  isset($_GET['created']) ? $this->sanitize->sanitizeInt($_GET['created']) : 0
        ]);

        $math = new Math();
        parent::saveOptions($options);
        $redirectURL = add_query_arg(
            [
                'page' => 'wpstg-settings',
                'tab' => 'remote-storages',
                'sub' => 'googledrive',
                'auth-storage' => 'true',
                'userDataToDisplay' => sprintf('Name: %s. Google Drive quota usage: %s used, %s available.', $this->getUserData(null, 'displayName'), $math->formatSize($this->getStorageInfo(null, 'usageInDrive')), $math->formatSize($this->getStorageInfo(null, 'limit')))
            ],
            admin_url('admin.php')
        );

        wp_redirect($redirectURL);
    }

    /**
     * Authenticate when user set his own API credentials
     */
    public function apiAuthenticate()
    {
        $options = $this->getOptions();
        $googleClient = new GoogleClient();
        $googleClient->setClientId($options['googleClientId']);
        $googleClient->setClientSecret($options['googleClientSecret']);
        $googleClient->setRedirectUri($options['googleRedirectURI']);
        $authorizedScopesRequiredAsArr = [
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/drive.file'
        ];

        $googleClient->setScopes($authorizedScopesRequiredAsArr);
        $googleClient->setAccessType('offline');

        $userAuthorizedScopesAsStr = filter_input(INPUT_GET, 'scope');
        $userAuthorizedScopesAsArr = array_filter(explode(' ', $userAuthorizedScopesAsStr));
        $isAuthorizedAllRequiredScopes = true;
        foreach ($authorizedScopesRequiredAsArr as $authorizedScopesRequired) {
            if (!in_array($authorizedScopesRequired, $userAuthorizedScopesAsArr)) {
                $isAuthorizedAllRequiredScopes =  false;
                break;
            }
        }

        if (!$isAuthorizedAllRequiredScopes) {
            echo sprintf('<strong style="font-family: arial,sans-serif;font-size:12px;">%s</strong>', esc_html__('You have not granted permissions required by the WP STAGING plugin. Please go back and retry the authorization.', 'wp-staging'));
            die;
        }

        $code = isset($_GET['code']) ? $this->sanitize->sanitizeString($_GET['code']) : '';
        $token = $googleClient->fetchAccessTokenWithAuthCode($code);
        $state = filter_input(INPUT_GET, 'state', FILTER_SANITIZE_URL);
        if (empty($token)) {
            $urlToRedirect = $state . '&action=wpstg-googledrive-auth-fail';
        } else {
            $urlToRedirect = $state . '&action=wpstg-googledrive-auth&access-token=' . base64_encode($token['access_token']) . '&refresh-token=' . base64_encode($token['refresh_token']) . '&expires-in=' . intval($token['expires_in']) . '&created=' . intval($token['created']);
        }

        header('Location: ' . $urlToRedirect);
    }

    /**
     * @param array $settings
     * @return bool
     */
    public function updateSettings($settings)
    {
        $options = $this->getOptions();
        $options['folderName'] = isset($settings['folder_name']) ? $settings['folder_name'] : self::FOLDER_NAME;
        $options['maxBackupsToKeep'] = isset($settings['max_backups_to_keep']) ? $settings['max_backups_to_keep'] : 0;
        $options['maxBackupsToKeep'] = $options['maxBackupsToKeep'] > 0 ? $options['maxBackupsToKeep'] : 15;
        $options['googleClientId'] = isset($settings['google_client_id']) ? $settings['google_client_id'] : '';
        $options['googleClientSecret'] = isset($settings['google_client_secret']) ? $settings['google_client_secret'] : '';
        $options['googleRedirectURI'] = isset($settings['google_redirect_uri']) ? $settings['google_redirect_uri'] : '';

        $options['lastUpdated'] = time();

        return $this->saveOptions($options);
    }

    /**
     * @param  GoogleDriveService $service
     * @param  string $attr
     * @return mixed
     */
    public function getUserData($service = null, $attr = null)
    {
        if ($service === null) {
            $service = new GoogleDriveService($this->setClientWithAuthToken());
        }
        $res = $service->about->get(['fields' => 'user'])->getUser();
        if ($attr && isset($res->$attr)) {
            return $res->$attr;
        }
        return $res;
    }

    /**
     * @param  GoogleDriveService $service
     * @param  string $attr
     * @return mixed
     */
    public function getStorageInfo($service = null, $attr = null)
    {
        if ($service === null) {
            $service = new GoogleDriveService($this->setClientWithAuthToken());
        }

        $res = $service->about->get(['fields' => 'storageQuota'])->getStorageQuota();
        if ($attr && isset($res->$attr)) {
            return $res->$attr;
        }
        return $res;
    }

    /**
     * Revoke both access and refresh token,
     * Also unauthenticate the provider
     * @return bool
     */
    public function revoke()
    {
        $options = $this->getOptions();
        if ($options['refreshToken'] !== '') {
            // revoke refresh token
            try {
                $this->client->revokeToken($options['refreshToken']);
            } catch (\Exception $ex) {
                $options['isAuthenticated'] = false;
                $options['accessToken']     = '';
                $options['refreshToken']    = '';

                parent::saveOptions($options);

                return true;
            }
        }

        $options['isAuthenticated'] = false;
        $options['accessToken']     = '';
        $options['refreshToken']    = '';

        parent::saveOptions($options);

        return true;
    }

    /**
     * @return GoogleClient
     */
    public function setClientWithAuthToken()
    {
        $options = $this->getOptions();
        $accessToken = [
            'access_token' => isset($options['accessToken']) ? $options['accessToken'] : 'someInvalidToken',
            'expires_in' => isset($options['expiresIn']) ? $options['expiresIn'] : 3599,
            'created' =>  (isset($options['created']) && is_int($options['created'])) ? $options['created'] : time() - 3600
        ];

        try {
            $this->client->setAccessToken($accessToken);
        } catch (InvalidArgumentException $ex) {
            debug_log($ex->getMessage());
            return $this->client;
        }

        if (!$this->client->isAccessTokenExpired()) {
            return $this->client;
        }

        // Check whether the backup process running
        try {
            $this->backupProcessLock->checkProcessLocked();
            $isBackupProcessRunning = true;
        } catch (ProcessLockedException $e) {
            $isBackupProcessRunning = false;
        }

        // Early bail if backup process not running
        // Filter wpstg.googleAuth.accessToken.validateOnEachRequest is for internal use only
        if (!$isBackupProcessRunning && !apply_filters('wpstg.googleAuth.accessToken.validateOnEachRequest', false)) {
            return $this->client;
        }

        $clientSecret = isset($options['googleClientSecret']) ? $options['googleClientSecret'] : '';
        if (apply_filters('wpstg.backup.storage.googledrive.client_secret', $clientSecret) === '') {
            $this->refreshAccessTokenRemotely($options['refreshToken']);
            return $this->client;
        }

        $this->refreshAccessToken($options['refreshToken']);
        return $this->client;
    }

    /** @return array */
    public function getFiles()
    {
        $this->setClientWithAuthToken();
        $options = $this->getOptions();
        $folderName = isset($options['folderName']) ? $options['folderName'] : self::FOLDER_NAME;
        $service = new GoogleDriveService($this->client);
        $folderId = $this->getFolderIdByName($folderName, $service);

        return $service->files->listFiles([
            'q'       => "'" . $folderId . "' in parents",
            'fields'  => 'nextPageToken, files(id, name, mimeType, size)'
        ]);
    }

    /**
     * Delete all backup files
     * Used by /tests/webdriverNew/Backup/GoogleDriveUploadCest.php
     * @return void
     * @throws Exception
     */
    public function cleanBackups()
    {
        $service = new GoogleDriveService($this->client);
        foreach ($this->getFiles() as $file) {
            $service->files->delete($file->getId());
        }
    }

    /**
     * @param $path
     * @param $service
     * @return false|string string|false
     * @throws Exception
     */
    public function getFolderIdByName($path, $service = null)
    {
        if ($service === null) {
            try {
                $service = new GoogleDriveService($this->client);
            } catch (\Exception $e) {
                return false;
            }
        }

        try {
            /** @var FileList */
            $response = $service->files->listFiles([
                'q' => "name ='" . $path . "' and 'root' in parents and mimeType = 'application/vnd.google-apps.folder'",
                'fields' => 'nextPageToken, files(id, name, mimeType)',
            ]);
        } catch (\Exception $e) {
            $this->revoke();
            throw new \Exception(sprintf(esc_html__('Can not upload files to Google Drive. Please reconnect Google Drive on WP STAGING > Settings > Remote Storage Providers. Error: %s', 'wp-staging'), $e->getMessage()));
        }


        if (sizeof($response->getFiles()) === 0) {
            $fileMetadata = new GoogleDriveFile([
                'name' => $path,
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);

            $file = $service->files->create($fileMetadata, [
                'fields' => 'id'
            ]);

            return $file->id;
        }

        foreach ($response->getFiles() as $file) {
            return $file->getId();
        }

        return false;
    }

    /**
     * @param $path
     * @param $service
     * @return false|string string|false
     * @throws Exception
     */
    public function getFileInfo($path, $service = null)
    {
        $this->setClientWithAuthToken();
        if ($service === null) {
            $service = new GoogleDriveService($this->client);
        }

        $options = $this->getOptions();
        $folderName = isset($options['folderName']) ? $options['folderName'] : self::FOLDER_NAME;
        $folderId = $this->getFolderIdByName($folderName, $service);

        $response = $service->files->listFiles([
            'q'       => "name ='" . $path . "' and '" . $folderId . "' in parents",
            'fields'  => 'nextPageToken, files(id, name, mimeType)'
        ]);

        foreach ($response->getFiles() as $file) {
            return $file->getId();
        }

        return false;
    }

    /**
     * This will refresh access token by directly calling Google OAuth api
     * @param string $refreshToken
     */
    public function refreshAccessToken($refreshToken)
    {
        $accessToken = null;
        try {
            $accessToken = $this->client->refreshToken($refreshToken);
        } catch (ClientException $ex) {
            debug_log($ex->getMessage());
            return false;
        } catch (ConnectException $ex) {
            debug_log($ex->getMessage());
            return false;
        }

        if (!is_null($accessToken)) {
            $options = $this->getOptions();
            $options['accessToken'] = $accessToken['access_token'];
            $options['created'] = $accessToken['created'];
            parent::saveOptions($options);

            return true;
        }

        return false;
    }

    /**
     * This will refresh access token by calling our auth api or
     * as specified by the user remotely
     * @param string $refreshToken
     */
    protected function refreshAccessTokenRemotely($refreshToken)
    {
        $config = [
            "verify" => WPSTG_PLUGIN_DIR . 'Pro/Backup/cacert.pem'
        ];

        $http = null;
        try {
            $http = new GuzzleClient($config);
        } catch (Exception $ex) {
            debug_log($ex->getMessage());
            return false;
        }

        $response = false;
        try {
            $response = $http->post(apply_filters('wpstg.backup.storage.googledrive.refresh_url', self::REFRESH_URL), [
                'form_params' => [
                    'refresh_token' => base64_encode($refreshToken)
                ]
            ]);
        } catch (ClientException $ex) {
            debug_log($ex->getMessage());
            return false;
        } catch (RequestException $ex) {
            debug_log($ex->getMessage());
            return false;
        } catch (Exception $ex) {
            debug_log($ex->getMessage());
            return false;
        }

        $responseJson = json_decode($response->getBody());
        if (!property_exists($responseJson, 'success')) {
            return false;
        }

        if ($responseJson->success !== true) {
            return false;
        }

        $accessToken = base64_decode($responseJson->accessToken);
        $created = $responseJson->created;
        if (!empty($accessToken)) {
            $options = $this->getOptions();
            $options['accessToken'] = $accessToken;
            $options['created'] = $created;
            parent::saveOptions($options);
            return true;
        }

        return false;
    }

    protected function maybeOverrideClientConfig()
    {
        $options = $this->getOptions();

        // only override the config from options,
        // if they are set in options and
        // no filters are applied already
        if (
            isset($options['googleClientId']) &&
            $options['googleClientId'] !== '' &&
            $this->client->getClientId() === self::CLIENT_ID
        ) {
            $this->client->setClientId($options['googleClientId']);
            $this->client->setClientSecret($options['googleClientSecret']);
            $this->client->setRedirectUri($options['googleRedirectURI']);
        }
    }
}
