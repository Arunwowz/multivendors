<?php

namespace WPStaging\Pro\Backup\Storage\Storages\GenericS3;

use WPStaging\Framework\Utils\Sanitize;
use WPStaging\Pro\Backup\Storage\Storages\BaseS3\S3Auth;

class Auth extends S3Auth
{
    public function __construct(Sanitize $sanitize)
    {
        $this->identifier = 'generic-s3';
        $this->label = 'Generic S3';
        parent::__construct($sanitize);
    }

    /**
     * Return list of regions
     * @return array
     */
    public function getRegions()
    {
        return [];
    }

    protected function setupProvider()
    {
        $providers = Providers::PROVIDERS;
        if (!array_key_exists($this->provider, $providers)) {
            return;
        }

        $provider = $providers[$this->provider];

        $this->ssl = $provider['ssl'];
        $this->usePathStyleEndpoint = $provider['usePathStyleEndpoint'];
        $this->endpoint = $provider['endpoint'];
        $this->version = $provider['version'];
    }
}
