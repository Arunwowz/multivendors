<?php

namespace WPStaging\Pro\Backup\Storage\Storages\Wasabi;

use WPStaging\Framework\Utils\Sanitize;
use WPStaging\Pro\Backup\Storage\Storages\BaseS3\S3Auth;

class Auth extends S3Auth
{
    protected $version = 'latest';

    /** @var null|string */
    protected $endpoint = 's3.[region]wasabisys.com';

    public function __construct(Sanitize $sanitize)
    {
        $this->identifier = 'wasabi';
        $this->label = 'Wasabi';
        parent::__construct($sanitize);
    }

    /**
     * Return list of regions supported by Wasabi S3
     * @return array
     *
     * @todo Refactor to use API when Wasabi S3 provide an API to fetch regions
     */
    public function getRegions()
    {
        return [
            'us-east-1'      => 'US East (N. Virginia)',
            'us-east-2'      => 'US East (N. Virginia)',
            'us-central-1'   => 'US Central (Texas)',
            'us-west-1'      => 'US West (Oregon)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ap-northeast-2' => 'Asia Pacific (Osaka)',
            'ca-central-1'   => 'Canada (Toronto)',
            'eu-central-1'   => 'Europe (Amsterdam)',
            'eu-central-2'   => 'Europe (Frankfurt)',
            'eu-west-1'      => 'Europe (London)',
            'eu-west-2'      => 'Europe (Paris)',
        ];
    }

    protected function setupProvider()
    {
        // no-op
    }
}
