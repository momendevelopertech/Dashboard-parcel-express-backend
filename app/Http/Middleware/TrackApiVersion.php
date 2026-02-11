<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackApiVersion
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $version = $request->segment(2);
        \Log::info('API version used', [
            'version' => $version,
            'path' => $request->path(),
            'user_id' => auth()->id(),
        ]);

        return $response;
    }
}
