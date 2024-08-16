<?php

namespace WPStaging\Pro\Backup\Storage\Storages\GenericS3;

interface Providers
{
    const PROVIDERS = [
        'cephio' => [
            'name' => 'Ceph.io',
            'key'  => 'cephio',
            'ssl'  => false,
            'endpoint'  => 'objects.dreamhost.com',
            'version'   => '2006-03-01',
            'usePathStyleEndpoint' => true,
        ],
    ];
}
