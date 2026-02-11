<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api_v1.php',   // ✅ رجعناه string
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            require base_path('routes/sandbox.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Set locale globally for all requests
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
        
        $middleware->api(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
        
        $middleware->alias([
            'authorize' => App\Http\Middleware\AuthorizeMiddleware::class,
            'role' => App\Http\Middleware\CheckRoleMiddleware::class,
            'workspace.valid' => \App\Http\Middleware\ValidateWorkspace::class,
            'partner.auth' => App\Http\Middleware\AuthenticatePartner::class,
            'verify.hmac' => App\Http\Middleware\VerifyHmacSignature::class,
            'rate.limit' => App\Http\Middleware\RateLimitPartner::class,
            'ip.allow' => App\Http\Middleware\IpAllowlist::class,
            'audit' => App\Http\Middleware\AuditRequest::class,
            'scopes' => App\Http\Middleware\ValidateScopes::class,
            'detect.env' => App\Http\Middleware\DetectEnvironment::class,
            'driver-not-on-hold' => \App\Http\Middleware\EnsureDriverNotOnHold::class,
            'exclude.role' => \App\Http\Middleware\ExcludeRole::class,
        ]);
    })
    ->withCommands([
        App\Console\Commands\MakePackage::class,
        App\Console\Commands\CronTestCommand::class,
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['auth:sanctum']]
    )
->create();