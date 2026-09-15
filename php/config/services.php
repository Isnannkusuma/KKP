<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'portal_pegawai' => [
        'base_url' => env('PORTAL_API_BASE_URL', ''),
        'token' => env('PORTAL_API_TOKEN', ''),
    ],

    'pemetaan_pegawai' => [
        'base_url' => env('PEMETAAN_API_KEY', env('PEMETAAN_API_BASE_URL', '')),
        'token' => env('PEMETAAN_API_TOKEN', ''),
    ],

    'bangkom_pegawai' => [
        'base_url' => env('BANGKOM_API_KEY', env('BANGKOM_API_BASE_URL', '')),
        'token' => env('BANGKOM_API_TOKEN', ''),
    ],

    'python_ai' => [
        'base_url' => env('PYTHON_AI_URL', 'http://localhost:8000'),
    ],

];
