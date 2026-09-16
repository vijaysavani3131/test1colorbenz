<?php

return [
    'default' => env('FILESYSTEM_DISK', 'private'),
    'disks' => [
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],
    ],
    'links' => [public_path('storage') => storage_path('app/public')],
];
