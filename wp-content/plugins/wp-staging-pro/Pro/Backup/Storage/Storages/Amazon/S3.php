<?php

namespace WPStaging\Pro\Backup\Storage\Storages\Amazon;

use WPStaging\Framework\Utils\Sanitize;
use WPStaging\Pro\Backup\Storage\Storages\BaseS3\S3Auth;

class S3 extends S3Auth
{
    /** @var string */
    protected $version = '2006-03-01';

    public function __construct(Sanitize $sanitize)
    {
        $this->identifier = 'amazons3';
        $this->label = 'Amazon S3';
        parent::__construct($sanitize);
    }

    /**
     * Return list of regions supported by Amazon S3
     * @return array
     *
     * @todo Refactor to use API when Amazon S3 provide an API to fetch regions
     */
    public function getRegions()
    {
        return [
            'us-east-1'      => 'US East (N. Virginia)',
            'us-east-2'      => 'US East (Ohio)',
            'us-west-1'      => 'US West (N. California)',
            'us-west-2'      => 'US West (Oregon)',
            'ap-east-1'      => 'Asia Pacific (Hong Kong)',
            'ap-south-1'     => 'Asia Pacific (Mumbai)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-southeast-3' => 'Asia Pacific (Jakarta)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ap-northeast-2' => 'Asia Pacific (Seoul)',
            'ap-northeast-3' => 'Asia Pacific (Osaka)',
            'ca-central-1'   => 'Canada (Central)',
            'eu-central-1'   => 'Europe (Frankfurt)',
            'eu-west-1'      => 'Europe (Ireland)',
            'eu-west-2'      => 'Europe (London)',
            'eu-west-3'      => 'Europe (Paris)',
            'eu-north-1'     => 'Europe (Stockholm)',
            'me-south-1'     => 'Middle East (Bahrain)',
            'sa-east-1'      => 'South America (São Paulo)',
        ];
    }

    protected function setupProvider()
    {
        // no-op
    }
}
