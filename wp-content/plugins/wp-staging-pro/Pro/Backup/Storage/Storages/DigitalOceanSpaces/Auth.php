<?php

namespace WPStaging\Pro\Backup\Storage\Storages\DigitalOceanSpaces;

use WPStaging\Framework\Utils\Sanitize;
use WPStaging\Pro\Backup\Storage\Storages\BaseS3\S3Auth;

class Auth extends S3Auth
{
    protected $version = 'latest';

    /** @var null|string */
    protected $endpoint = '[region]digitaloceanspaces.com';

    /** @var bool */
    protected $usePathStyleEndpoint = false;

    public function __construct(Sanitize $sanitize)
    {
        $this->identifier = 'digitalocean-spaces';
        $this->label = 'DigitalOcean Spaces';
        parent::__construct($sanitize);
    }

    /**
     * Return list of regions supported by DigitalOcean Spaces
     * @return array
     *
     * @todo Refactor to use API when DigitalOcean Spaces provide an API to fetch regions
     * @see https://filezillapro.com/how-to-connect-to-digitalocean-spaces/
     */
    public function getRegions()
    {
        return [
            'ams3' => 'ams3 (Amsterdam)',
            'blr1' => 'blr1 (Bangalore)',
            'fra1' => 'fra1 (Frankfurt)',
            'lon1' => 'lon1 (London)',
            'nyc1' => 'nyc1 (New York 1)',
            'nyc3' => 'nyc3 (New York 3)',
            'sfo2' => 'sfo2 (San Francisco 2)',
            'sfo3' => 'sfo3 (San Francisco 3)', // From Digital Ocean own documentation
            'sgp1' => 'sgp1 (Singapore)',
            'tor1' => 'tor1 (Toronto)',
        ];
    }

    protected function setupProvider()
    {
        // no-op
    }
}
