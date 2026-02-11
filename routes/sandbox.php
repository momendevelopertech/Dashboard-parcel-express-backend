<?php

use Illuminate\Support\Facades\Route;

// Sandbox environment routes for API
// Production: https://sandbox-api.parcelexpress.com/{version}
// Local: https://sandbox-api.localhost:9000/{version} (port handled by web server)
// Version can be changed in config/api.php or via SANDBOX_API_VERSION env variable
$sandboxDomain = config('app.env') === 'production'
    ? config('api.sandbox.domain.production')
    : config('api.sandbox.domain.local');

$sandboxVersion = config('api.sandbox.version', 'v1');
$sandboxRouteFile = config('api.sandbox.route_file', 'api_v1.php');

// Register sandbox routes with domain matching
// Note: Laravel's domain() only matches hostname, not port
// The domain should match the Host header from the request
Route::domain($sandboxDomain)
    ->middleware('api')
    ->prefix($sandboxVersion)
    ->group(function () use ($sandboxRouteFile) {
        require base_path('routes/' . $sandboxRouteFile);
    });

