<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        // 如需云存储，可在此添加 's3' 等磁盘配置
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];


