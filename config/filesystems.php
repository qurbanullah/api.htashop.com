<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
            'report' => false,
        ],

        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
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

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],
        'idrivee2' => [
            'driver' => 's3',
            'key' => env('IDRIVEE2_ACCESS_KEY_ID'),
            'secret' => env('IDRIVEE2_SECRET_ACCESS_KEY'),
            'region' => env('IDRIVEE2_DEFAULT_REGION'),
            'bucket' => env('IDRIVEE2_BUCKET'),
            'url' => env('IDRIVEE2_PUBLIC_URL'),
            'endpoint' => env('IDRIVEE2_ENDPOINT'),
            'use_path_style_endpoint' => env('IDRIVEE2_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'options' => [
                'http' => [
                    'connect_timeout' => 30,
                    'timeout' => 120,
                    'verify' => env('IDRIVEE2_SSL_VERIFY', true),
                ],
                'retries' => [
                    'mode' => 'adaptive',
                    'max_attempts' => 5,
                ],
            ],
        ],

        'journals' => [
            'driver' => 'local',
            'root' => storage_path('app/journals'),
            'url' => env('APP_URL').'/storage/journals',
            'visibility' => 'private', // Journal files should be private by default
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
