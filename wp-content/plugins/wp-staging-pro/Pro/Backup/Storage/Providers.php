<?php

namespace WPStaging\Pro\Backup\Storage;

use WPStaging\Pro\Backup\Storage\Storages\Amazon\S3;
use WPStaging\Pro\Backup\Storage\Storages\GoogleDrive\Auth as GoogleDriveAuth;
use WPStaging\Pro\Backup\Storage\Storages\SFTP\Auth as SftpAuth;
use WPStaging\Pro\Backup\Storage\Storages\DigitalOceanSpaces\Auth as DSOAuth;
use WPStaging\Pro\Backup\Storage\Storages\Wasabi\Auth as WasabiAuth;
use WPStaging\Pro\Backup\Storage\Storages\GenericS3\Auth as GenericS3Auth;

class Providers
{
    protected $storages = [];

    /**
     * @param GoogleDriveAuth $googleAuth
     * @param S3 $amazonS3
     * @param SftpAuth $sftpAuth
     * @param DSOAuth $dsoAuth
     * @param WasabiAuth $wasabiAuth
     * @param GenericS3Auth $genericS3Auth
     *
     * @todo Find a better way to inject these providers
     */
    public function __construct(GoogleDriveAuth $googleAuth, S3 $amazonS3, SftpAuth $sftpAuth, DSOAuth $dsoAuth, WasabiAuth $wasabiAuth, GenericS3Auth $genericS3Auth)
    {
        $this->storages = [
            [
                'id'   => 'googleDrive',
                'name' => esc_html__('Google Drive'),
                'enabled' => true,
                'activated' => $googleAuth->isAuthenticated(),
                'settingsPath'  => $this->getStorageAdminPage('googleDrive'),
                'authClass' => GoogleDriveAuth::class
            ],
            [
                'id'   => 'amazonS3',
                'name' => esc_html__('Amazon S3'),
                'enabled' => true,
                'activated' => $amazonS3->isAuthenticated(),
                'settingsPath'  => $this->getStorageAdminPage('amazonS3'),
                'authClass' => S3::class
            ],
            [
                'id'   => 'dropbox',
                'name' => esc_html__('Dropbox'),
                'enabled' => false,
                'activated' => false,
                'settingsPath'  => $this->getStorageAdminPage('dropbox'),
                'authClass' => ''
            ],
            [
                'id'   => 'oneDrive',
                'name' => esc_html__('One Drive'),
                'enabled' => false,
                'activated' => false,
                'settingsPath'  => $this->getStorageAdminPage('onedrive'),
                'authClass' => ''
            ],
            [
                'id'   => 'sftp',
                'name' => esc_html__('FTP / SFTP'),
                'enabled' => true,
                'activated' => $sftpAuth->isAuthenticated(),
                'settingsPath'  => $this->getStorageAdminPage('sftp'),
                'authClass' => SftpAuth::class
            ],
            [
                'id'   => 'digitalocean-spaces',
                'name' => esc_html__('DigitalOcean Spaces'),
                'enabled' => true,
                'activated' => $dsoAuth->isAuthenticated(),
                'settingsPath'  => $this->getStorageAdminPage('digitalocean-spaces'),
                'authClass' => DSOAuth::class
            ],
            [
                'id'   => 'wasabi-s3',
                'name' => esc_html__('Wasabi S3'),
                'enabled' => true,
                'activated' => $wasabiAuth->isAuthenticated(),
                'settingsPath'  => $this->getStorageAdminPage('wasabi-s3'),
                'authClass' => WasabiAuth::class
            ],
            [
                'id'   => 'generic-s3',
                'name' => esc_html__('Generic S3'),
                'enabled' => true,
                'activated' => $genericS3Auth->isAuthenticated(),
                'settingsPath'  => $this->getStorageAdminPage('generic-s3'),
                'authClass' => GenericS3Auth::class
            ],
        ];
    }

    /**
     * @param null|bool $isEnabled. Default null
     *                  Use null for all storages,
     *                  Use true for enabled storages,
     *                  Use false for disabled storages
     *
     * @return array
     */
    public function getStorageIds($isEnabled = null)
    {
        return array_map(function ($storage) {
            return $storage['id'];
        }, $this->getStorages($isEnabled));
    }

    /**
     * @param null|bool $isEnabled. Default null
     *                  Use null for all storages,
     *                  Use true for enabled storages,
     *                  Use false for disabled storages
     *
     * @return array
     */
    public function getStorages($isEnabled = null)
    {
        if ($isEnabled === null) {
            return $this->storages;
        }

        return array_filter($this->storages, function ($storage) use ($isEnabled) {
            return $storage['enabled'] === $isEnabled;
        });
    }

    /**
     * @param string $id
     * @param string $property
     * @param null|bool $isEnabled. Default null
     *                  Use null for all storages,
     *                  Use true for enabled storages,
     *                  Use false for disabled storages
     *
     * @return mixed
     */
    public function getStorageProperty($id, $property, $isEnabled = null)
    {
        foreach ($this->getStorages($isEnabled) as $storage) {
            if ($storage['id'] === $id) {
                if (array_key_exists($property, $storage)) {
                    return $storage[$property];
                }
            }
        }

        return false;
    }

    private function getStorageAdminPage($storageTab)
    {
        return admin_url('admin.php?page=wpstg-settings&tab=remote-storages&sub=' . $storageTab);
    }
}
