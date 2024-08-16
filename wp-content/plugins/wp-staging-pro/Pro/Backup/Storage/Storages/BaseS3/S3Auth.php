<?php

namespace WPStaging\Pro\Backup\Storage\Storages\BaseS3;

use WPStaging\Pro\Backup\Storage\AbstractStorage;
use WPStaging\Vendor\Aws\S3\S3Client;
use Exception;
use WPStaging\Framework\Utils\Sanitize;

use function WPStaging\functions\debug_log;

abstract class S3Auth extends AbstractStorage
{
    /** @var string */
    protected $version = 'latest';

    /** @var string */
    protected $provider = '';

    /** @var null|string */
    protected $endpoint = null;

    /** @var bool */
    protected $ssl = true;

    /** @var bool */
    protected $usePathStyleEndpoint = true;

    /** @var string */
    protected $bucketName;

    /** @var Sanitize */
    protected $sanitize;

    /** @var S3Client */
    protected $client;

    public function __construct(Sanitize $sanitize)
    {
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
        try {
            $endpoint = isset($_POST['endpoint']) ? $this->sanitize->sanitizeString($_POST['endpoint']) : '';
            $this->provider = isset($_POST['s3_provider']) ? $this->sanitize->sanitizeString($_POST['s3_provider']) : '';
            $this->version = isset($_POST['version']) ? $this->sanitize->sanitizeString($_POST['version']) : $this->version;
            $this->ssl = isset($_POST['ssl']) ? $this->sanitize->sanitizeBool($_POST['ssl']) : $this->ssl;
            $this->usePathStyleEndpoint = isset($_POST['use_path_style_endpoint']) ? $this->sanitize->sanitizeBool($_POST['use_path_style_endpoint']) : $this->usePathStyleEndpoint;
            $accessKey = isset($_POST['access_key']) ? $this->sanitize->sanitizePassword($_POST['access_key']) : '';
            $secretKey = isset($_POST['secret_key']) ? $this->sanitize->sanitizePassword($_POST['secret_key']) : '';
            $region = isset($_POST['region']) ? $this->sanitize->sanitizeString($_POST['region']) : '';

            // Instantiate the S3 client with your AWS credentials
            $s3Client = new S3Client($this->getConfigOptions($accessKey, $secretKey, $region, $endpoint));

            $buckets = $s3Client->listBuckets();
        } catch (Exception $ex) {
            debug_log("S3 Client : " . $ex->getMessage());
            return false;
        }

        return true;
    }

    /** @return S3Client|false */
    public function getClient($options = null)
    {
        if ($options === null) {
            $options = $this->getOptions();
        }

        $this->provider = isset($options['provider']) ? $options['provider'] : '';
        $this->ssl = isset($options['ssl']) ? $options['ssl'] : $this->ssl;
        $this->version = isset($options['version']) ? $options['version'] : $this->version;
        $this->usePathStyleEndpoint = isset($options['usePathStyleEndpoint']) ? $options['usePathStyleEndpoint'] : $this->usePathStyleEndpoint;

        $endpoint = (isset($options['endpoint']) && $this->endpoint !== $options['endpoint']) ? $options['endpoint'] : null;

        try {
            // Instantiate the S3 client with your AWS credentials
            $s3Client = new S3Client($this->getConfigOptions($options['accessKey'], $options['secretKey'], $options['region'], $endpoint));
        } catch (Exception $ex) {
            debug_log($ex->getMessage());
            return false;
        }

        return $s3Client;
    }

    /**
     * @param array $settings
     * @return bool
     */
    public function updateSettings($settings)
    {
        $options = $this->getOptions();
        $s3Provider = isset($settings['s3_provider']) ? $this->sanitize->sanitizeString($settings['s3_provider']) : '';
        $endpoint = isset($settings['endpoint']) ? $this->sanitize->sanitizeString($settings['endpoint']) : '';
        $accessKey = isset($settings['access_key']) ? $this->sanitize->sanitizePassword($settings['access_key']) : '';
        $secretKey = isset($settings['secret_key']) ? $this->sanitize->sanitizePassword($settings['secret_key']) : '';
        $region = isset($settings['region']) ? $this->sanitize->sanitizeString($settings['region']) : '';
        $location = isset($settings['location']) ? $this->sanitize->sanitizePath($settings['location']) : '';
        $backupsToKeep = isset($settings['max_backups_to_keep']) ? $this->sanitize->sanitizeInt($settings['max_backups_to_keep']) : 2;
        $backupsToKeep = $backupsToKeep > 0 ? $backupsToKeep : 15;

        $options['location'] = $location;
        $options['region'] = $region;
        $options['accessKey'] = $accessKey;
        $options['secretKey'] = $secretKey;
        $options['provider'] = $s3Provider;
        $options['endpoint'] = empty($endpoint) ? $this->endpoint : $endpoint;
        $options['maxBackupsToKeep'] = $backupsToKeep;

        if (isset($settings['provider_name'])) {
            $options['providerName'] = $this->sanitize->sanitizeString($settings['provider_name']);
        }

        if (isset($settings['ssl'])) {
            $options['ssl'] = $this->sanitize->sanitizeBool($settings['ssl']);
        }

        if (isset($settings['version'])) {
            $options['version'] = $this->sanitize->sanitizeString($settings['version']);
        }

        if (isset($settings['use_path_style_endpoint'])) {
            $options['usePathStyleEndpoint'] = $this->sanitize->sanitizeBool($settings['use_path_style_endpoint']);
        }

        $options['isAuthenticated'] = false;

        $client = $this->getClient($options);
        if ($client !== false) {
            $options['isAuthenticated'] = true;
        }

        $options['lastUpdated'] = time();

        return $this->saveOptions($options);
    }

    /**
     * Revoke both access and refresh token,
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
        $options['accessKey'] = '';
        $options['secretKey'] = '';
        $options['region']    = '';
        $options['location']  = '';
        $options['provider']  = '';
        $options['endpoint']  = '';
        $options['version']   = '';
        $options['ssl']       = null;
        $options['usePathStyleEndpoint'] = null;

        return parent::saveOptions($options);
    }

    public function getFiles()
    {
        $options = $this->getOptions();
        $client = $this->getClient($options);
        if ($client === false) {
            return;
        }

        $this->client = $client;
        list($bucketName, $path) = $this->getLocation();
        $this->bucketName = $bucketName;

        try {
            $searchParams = [
                'Bucket' => $bucketName,
            ];

            if (!empty($path)) {
                $searchParams['Prefix'] = $path;
            }

            $result = $client->listObjects($searchParams);

            // array key is lazy loaded
            $backups = $result['Contents'];

            if (empty($backups)) {
                return [];
            }

            if (!is_array($backups)) {
                return [];
            }

            return $backups;
        } catch (Exception $ex) {
            debug_log('Error listing S3 objects: ' . $ex->getMessage());
            return [];
        }

        return [];
    }

    /**
     * Delete all backup files
     * Used by /tests/webdriverNew/Backup/AmazonS3UploadCest.php
     * @return void
     */
    public function cleanBackups()
    {
        try {
            $objects = [];
            foreach ($this->getFiles() as $object) {
                $objects[] = [
                    'Key' => $object['Key']
                ];
            }

            $this->client->deleteObjects([
                'Bucket' => $this->bucketName,
                'Delete' => [
                    'Objects' => $objects,
                ],
            ]);
        } catch (Exception $ex) {
            return;
        }

        debug_log('Could not find backup. Error: ' . $this->bucketName);
        return false;
    }

    /**
     * @param $backupFile
     * @return bool
     */
    public function isBackupUploaded($backupFile)
    {
        $options = $this->getOptions();
        $client = $this->getClient($options);
        if ($client === false) {
            return false;
        }

        list($bucketName, $path) = $this->getLocation();

        try {
            $searchParams = [
                'Bucket' => $bucketName,
            ];

            $prefix = '';
            if (!empty($path)) {
                $prefix = $path;
                $searchParams['Prefix'] = $prefix;
            }

            $result = $client->listObjects($searchParams);

            $objects = $result['Contents'];

            foreach ($objects as $object) {
                if ($prefix . $backupFile === $object['Key']) {
                    return true;
                }
            }
        } catch (Exception $ex) {
            debug_log($ex->getMessage());
        }

        debug_log('Could not find backup. Error: ' . $bucketName);
        return false;
    }

    /** @return array Bucket Name of 0 index and rest of path on 1 index */
    public function getLocation()
    {
        $options = $this->getOptions();
        $location = $this->explodeLocation($options['location']);
        $bucketName = $location[0];
        $path = '';
        for ($i = 1; $i < count($location); $i++) {
            $path .= $location[$i] . '/';
        }

        return [$bucketName, $path];
    }

    /**
     * Return list of regions supported by Provider
     * @return array
     */
    public function getRegions()
    {
        return [];
    }

    abstract protected function setupProvider();

    protected function getEndpoint($region)
    {
        if (empty($this->endpoint)) {
            return null;
        }

        $scheme = $this->ssl ? 'https' : 'http';
        $region = empty($region) ? '' : ($region . '.');

        return $scheme . '://' . str_replace('[region]', $region, $this->endpoint);
    }

    /**
     * Get configuration object
     *
     * @return array
     */
    protected function getConfigOptions($accessKey, $secretKey, $region, $endpoint = null)
    {
        $this->setupProvider();

        $config = [
            'version'     => $this->version,
            'region'      => $region,
            'endpoint'    => empty($endpoint) ? $this->getEndpoint($region) : $endpoint,
            'credentials' => [
                'key'    => $accessKey,
                'secret' => $secretKey,
            ],
            'use_path_style_endpoint' => $this->usePathStyleEndpoint,
        ];

        if ($this->ssl) {
            $config['http'] =  [
                'verify' => WPSTG_PLUGIN_DIR . 'Pro/Backup/cacert.pem'
            ];
        }

        return $config;
    }

    private function explodeLocation($location)
    {
        return explode('/', $location);
    }
}
