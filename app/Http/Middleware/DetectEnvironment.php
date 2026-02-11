<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class DetectEnvironment
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-KEY');

        // Detect environment based on API key prefix
        if (Str::startsWith($apiKey, 'test_'))
        {
            app()->instance('env.context', 'sandbox');
        } elseif (Str::startsWith($apiKey, 'live_')) {
            app()->instance('env.context', 'production');
        } else {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Set default database connection dynamically
        $env = app('env.context');
        $connection = $env === 'sandbox' ? 'mysql_sandbox' : 'mysql';
        config(['database.default' => $connection]);

        return $next($request);
    }

}
