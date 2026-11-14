<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "s3", "rackspace"
    |
    */

    'disks' => [

        'code_originals' => [
            'driver' => env('CODE_ORIGINALS_DRIVER', 'local'),
            'root' => env('CODE_ORIGINALS_DRIVER', 'local') === 'local' ? storage_path('app-private/code-originals') : env('CODE_ORIGINALS_PREFIX', 'originals'),
            'key' => env('CODE_S3_ACCESS_KEY_ID'),
            'secret' => env('CODE_S3_SECRET_ACCESS_KEY'),
            'region' => env('CODE_S3_REGION', 'ru-central1'),
            'bucket' => env('CODE_S3_BUCKET'),
            'endpoint' => env('CODE_S3_ENDPOINT'),
            'use_path_style_endpoint' => env('CODE_S3_PATH_STYLE', false),
            'visibility' => 'private',
            'throw' => true,
        ],

        'code_checking' => [
            'driver' => 'local',
            'root' => storage_path('app-private/code-checking'),
            'visibility' => 'private',
            'throw' => true,
        ],

        // Avatar snapshots must stay outside the root exposed by /media/{dir}/{name}.
        'room_character_sources' => [
            'driver' => 'local',
            'root' => storage_path('app-private/room-character-sources'),
            'visibility' => 'private',
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_KEY'),
            'secret' => env('AWS_SECRET'),
            'region' => env('AWS_REGION'),
            'bucket' => env('AWS_BUCKET'),
        ],

    ],

];
