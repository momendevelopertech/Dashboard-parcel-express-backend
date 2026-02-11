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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
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
    'ultramsg' => [
        'instance_id' => env('ULTRAMSG_INSTANCE_ID'),
        'token' => env('ULTRAMSG_TOKEN'),
    ],

    'zentramsg' => [
        'api_key' => env('ZENTRAMSG_API_KEY', "c0d5314e-5030-4e72-86ac-56304c4b7686"),
        'instance_id' => env('ZENTRAMSG_INSTANCE_ID', "1234567890"),
        'base_url' => env('ZENTRAMSG_BASE_URL', 'https://api.zentramsg.com/v1/messages'),
        'device_uuid' => env('ZENTRAMSG_DEVICE_UUID', 'f17874f5-338a-4581-b259-a2fb3c1e6733'),
    ],

    'whatsapp_cloud' => [
        'access_token' => env('WHATSAPP_CLOUD_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v23.0'),
    ],
    'google' => [
        'merchant_id' => env('GOOGLE_MERCHANT_ID'),
        'merchant_secret' => env('GOOGLE_MERCHANT_SECRET'),
        'redirect' => null
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'api_url' => env('GEMINI_API_URL'),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL'),
        'url' => env('GROQ_API_URL'),
    ],
];
