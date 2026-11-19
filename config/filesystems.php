<?php

declare(strict_types=1);

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
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Plan 1.12, D3: destino local de los respaldos, mientras TI no tenga el bucket. La página de salud lo muestra en
        // amarillo: no protege ante la pérdida del servidor.
        'respaldos' => [
            'driver' => 'local',
            'root' => storage_path('app/private/respaldos'),
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],

        // Plan 1.12, D3: el bucket de TI, de solo escritura y con versionado (docs/operaciones/respaldos.md). Se usa con
        // RESPALDOS_DISCO=respaldos_s3 (paquete league/flysystem-aws-s3-v3, desde la 1.12b).
        'respaldos_s3' => [
            'driver' => 's3',
            'key' => env('RESPALDOS_S3_KEY'),
            'secret' => env('RESPALDOS_S3_SECRET'),
            'region' => env('RESPALDOS_S3_REGION'),
            'bucket' => env('RESPALDOS_S3_BUCKET'),
            'endpoint' => env('RESPALDOS_S3_ENDPOINT'),
            'use_path_style_endpoint' => (bool) env('RESPALDOS_S3_PATH_STYLE', false),
            'throw' => true,
            'report' => false,
        ],

        // Archivo histórico (plan 1.4y, D6): lo que sale de la base al limpiar, cifrado; nunca se borra. Hoy en el servidor;
        // a S3 con HISTORICO_DISCO cuando TI tenga el bucket (con la 1.12b).
        'historico' => [
            'driver' => 'local',
            'root' => storage_path('app/private/historico'),
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],

        // Adjuntos (plan 1.8, D1): privados, nunca servidos directo; se descargan solo por la ruta que revisa permisos.
        // El respaldo a S3 lo hace el job de respaldos. Pasar a S3 como disco es cambiar el driver aquí.
        'adjuntos' => [
            'driver' => env('ADJUNTOS_DISCO_DRIVER', 'local'),
            'root' => storage_path('app/private/adjuntos'),
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
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
