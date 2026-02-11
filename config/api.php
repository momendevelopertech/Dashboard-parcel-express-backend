<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sandbox API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the sandbox API environment. These URLs determine
    | where the sandbox API is accessible based on the application environment.
    |
    */

    'sandbox' => [
        'domain' => [
            'local' => env('SANDBOX_API_DOMAIN_LOCAL', 'sandbox-api.localhost'),
            'production' => env('SANDBOX_API_DOMAIN_PRODUCTION', 'sandbox-api.parcelexpress.com'),
        ],
        'version' => env('SANDBOX_API_VERSION', 'v1'),
        'route_file' => env('SANDBOX_API_ROUTE_FILE', 'api_v1.php'),
    ],

];

