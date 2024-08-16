<?php

namespace WPStaging\Pro\Backup\Storage\Storages\DigitalOceanSpaces;

use WPStaging\Framework\Utils\Strings;
use WPStaging\Pro\Backup\Storage\Storages\BaseS3\S3Uploader as BaseS3Uploader;

class Uploader extends BaseS3Uploader
{
    public function __construct(Auth $auth, Strings $strings)
    {
        parent::__construct($auth, $strings);
    }

    public function getProviderName()
    {
        return 'DigitalOcean Spaces';
    }
}
