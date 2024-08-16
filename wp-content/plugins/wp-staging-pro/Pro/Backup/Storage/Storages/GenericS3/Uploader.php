<?php

namespace WPStaging\Pro\Backup\Storage\Storages\GenericS3;

use WPStaging\Framework\Utils\Strings;
use WPStaging\Pro\Backup\Storage\Storages\BaseS3\S3Uploader;

class Uploader extends S3Uploader
{
    public function __construct(Auth $auth, Strings $strings)
    {
        parent::__construct($auth, $strings);
    }

    public function getProviderName()
    {
        return 'Generic S3';
    }
}
