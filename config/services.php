<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AWS SDK Configuration
    |--------------------------------------------------------------------------
    */

    'credentials' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'token' => env('AWS_SESSION_TOKEN'),
    ],

    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    'version' => 'latest',

    'ua_append' => [
        'L11/' . app()->version(),
    ],

    'endpoint' => env('AWS_ENDPOINT'),

    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),

    /*
    |--------------------------------------------------------------------------
    | AWS Service Providers
    |--------------------------------------------------------------------------
    */

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
