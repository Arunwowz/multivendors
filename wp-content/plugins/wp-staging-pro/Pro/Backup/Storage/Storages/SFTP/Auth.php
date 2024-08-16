<?php

namespace WPStaging\Pro\Backup\Storage\Storages\SFTP;

use WPStaging\Pro\Backup\Storage\AbstractStorage;
use Exception;
use WPStaging\Framework\Utils\Sanitize;
use WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients\ClientInterface;
use WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients\FtpClient;
use WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients\FtpCurlClient;
use WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients\FtpException;
use WPStaging\Pro\Backup\Storage\Storages\SFTP\Clients\SftpClient;

use function WPStaging\functions\debug_log;

class Auth extends AbstractStorage
{
    /** @var Sanitize */
    protected $sanitize;

    /** @var ClientInterface */
    protected $client;

    public function __construct(Sanitize $sanitize)
    {
        $this->identifier = 'sftp';
        $this->label = 'FTP / SFTP';
        $this->sanitize = $sanitize;
    }

    public function authenticate()
    {
        // no-op
    }

    /**
     * @return bool
     */
    public function testConnection()
    {
        $options = [];
        $options['ftpType'] = isset($_POST['ftp_type']) ? $this->sanitize->sanitizeString($_POST['ftp_type']) : '';
        $options['host'] = isset($_POST['host']) ? $this->sanitize->sanitizeString($_POST['host']) : '';
        $options['port'] = isset($_POST['port']) ? $this->sanitize->sanitizeInt($_POST['port']) : '';
        $options['username'] = isset($_POST['username']) ? $this->sanitize->sanitizeString($_POST['username']) : '';
        $options['password'] = isset($_POST['password']) ? $this->sanitize->sanitizePassword($_POST['password']) : '';
        $options['passphrase'] = isset($_POST['passphrase']) ? $this->sanitize->sanitizeString($_POST['passphrase']) : '';
        $options['key'] = isset($_POST['key']) ? $this->sanitize->sanitizeString($_POST['key']) : '';
        $options['ssl'] = !empty($_POST['ssl']) && $this->sanitize->sanitizeBool($_POST['ssl']);
        $options['passive'] = !empty($_POST['passive']) && $this->sanitize->sanitizeBool($_POST['passive']);

        $client = $this->getClient($options);
        if ($client === false) {
            return false;
        }

        $result = $client->login();
        if ($result === false) {
            debug_log("Test Connection Error: " . $client->getError());
        }

        return $result;
    }

    /**
     * @param array $options Optional
     *
     * @return ClientInterface|false
     */
    public function getClient($options = null)
    {
        if ($options === null) {
            $options = $this->getOptions();
        }

        if ($options['ftpType'] === 'sftp') {
            return new SftpClient($options['host'], $options['username'], $options['password'], $options['key'], $options['passphrase'], $options['port']);
        }

        if (apply_filters('wpstg.ftpclient.forceUseFtpExtension', false) === false) {
            try {
                return new FtpCurlClient($options['host'], $options['username'], $options['password'], $options['ssl'], $options['passive'], $options['port']);
            } catch (FtpException $ex) {
                debug_log("Curl Extension Not Loaded");
            }
        }

        try {
            return new FtpClient($options['host'], $options['username'], $options['password'], $options['ssl'], $options['passive'], $options['port']);
        } catch (FtpException $ex) {
            debug_log("FTP Extension Not Loaded");
        }

        return false;
    }

    /**
     * @param array $settings
     * @return bool
     */
    public function updateSettings($settings)
    {
        $options = $this->getOptions();
        $ftpType = !empty($settings['ftp_type']) ? $this->sanitize->sanitizeString($settings['ftp_type']) : '';
        $host = !empty($settings['host']) ? $this->sanitize->sanitizeString($settings['host']) : '';
        $port = !empty($settings['port']) ? $this->sanitize->sanitizeInt($settings['port']) : '';
        $username = !empty($settings['username']) ? $this->sanitize->sanitizeString($settings['username']) : '';
        $password = !empty($settings['password']) ? $this->sanitize->sanitizePassword($settings['password']) : null;
        $key = !empty($settings['key']) ? $settings['key'] : null;
        $ssl = isset($settings['ssl']) ? $this->sanitize->sanitizeBool($settings['ssl']) : false;
        $passive = isset($settings['passive']) ? $this->sanitize->sanitizeBool($settings['passive']) : false;
        $passphrase = !empty($settings['passphrase']) ? $settings['passphrase'] : null;
        $location = isset($settings['location']) ? $this->sanitize->sanitizeString($settings['location']) : null;
        $backupsToKeep = isset($settings['max_backups_to_keep']) ? $this->sanitize->sanitizeInt($settings['max_backups_to_keep']) : 2;
        $backupsToKeep = $backupsToKeep > 0 ? $backupsToKeep : 15;

        $options['ftpType'] = $ftpType;
        $options['host'] = $host;
        $options['port'] = $port;
        $options['username'] = $username;
        $options['password'] = $password;
        $options['passphrase'] = $passphrase;
        $options['key'] = $key;
        $options['location'] = $location;
        $options['maxBackupsToKeep'] = $backupsToKeep;
        $options['ssl'] = $ssl;
        $options['passive'] = $passive;

        $options['isAuthenticated'] = false;

        $client = $this->getClient($options);
        if ($client !== false) {
            $result = $client->login();
            if ($result === false) {
                debug_log($client->getError());
            }

            $options['isAuthenticated'] = $result;
        }

        $options['lastUpdated'] = time();

        return $this->saveOptions($options);
    }

    /**
     * Clean all FTP / SFTP Settings,
     * Also unauthenticate the provider
     */
    public function revoke()
    {
        $options = $this->getOptions();

        // Early bail if already unauthenticated
        if ($options['isAuthenticated'] === false) {
            return true;
        }

        $options['isAuthenticated'] = false;
        $options['ftpType'] = '';
        $options['host'] = '';
        $options['username'] = '';
        $options['password'] = '';
        $options['key'] = '';
        $options['passphrase'] = '';
        $options['location'] = '';

        return parent::saveOptions($options);
    }

    public function getFiles()
    {
        $this->error = '';

        $options = $this->getOptions();
        $this->client = $this->getClient($options);
        $this->client->login();

        $files = $this->client->getFiles($options['location']);
        if (!is_array($files)) {
            $this->error = $this->client->getError();
            return [];
        }

        return $files;
    }

    public function cleanBackups()
    {
        $options = $this->getOptions();
        $files = $this->getFiles();
        $this->client->setPath($options['location']);
        foreach ($files as $file) {
            $result = $this->client->deleteFile($file['name']);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }
}
