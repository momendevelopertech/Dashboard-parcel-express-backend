<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $ms = (int) ((microtime(true) - $start) * 1000);
        $partnerId = optional($request->attributes->get('partner'))->id;
        Log::info('partner_api', [
            'partner_id' => $partnerId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->getPathInfo(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $ms,
        ]);
        return $next($request);
    }
}
