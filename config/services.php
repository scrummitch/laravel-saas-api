<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'client_id' => env('STRIPE_LIVE_CLIENT_ID'),
        'client_secret' => env('STRIPE_LIVE_CLIENT_SECRET'),
        'webhook_secret' => env('STRIPE_LIVE_WEBHOOK_SECRET'),
        'webhook_id' => env('STRIPE_LIVE_WEBHOOK_ID'),
        'redirect' => '',
    ],

    'stripe_test' => [
        'webhook_secret' => env('STRIPE_TEST_WEBHOOK_SECRET'),
        'client_id' => env('STRIPE_TEST_CLIENT_ID'),
        'client_secret' => env('STRIPE_TEST_CLIENT_SECRET'),
        'webhook_id' => env('STRIPE_TEST_WEBHOOK_ID'),
        'redirect' => '',
    ],

    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],

    'plandalf' => [
        'secret' => env('PLANDALF_SECRET', ''),
        'client_id' => env('PLANDALF_CLIENT_ID', ''),
    ],
];
