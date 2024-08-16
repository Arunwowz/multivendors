<?php

namespace WPStaging\Pro\Backup\Storage\Storages\Amazon;

use WPStaging\Framework\Utils\Strings;
use WPStaging\Pro\Backup\Storage\Storages\BaseS3\S3Uploader as BaseS3Uploader;

class S3Uploader extends BaseS3Uploader
{
    public function __construct(S3 $auth, Strings $strings)
    {
        parent::__construct($auth, $strings);
    }

    public function getProviderName()
    {
        return 'Amazon S3';
    }
}
