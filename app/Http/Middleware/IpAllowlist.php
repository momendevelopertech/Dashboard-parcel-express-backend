<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpAllowlist
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $request->attributes->get('partner');
        $ips = collect(data_get($partner->rate_limit, 'allow_ips', []));
        if ($ips->isNotEmpty() && !$ips->contains($request->ip())) {
            return response()->json(['code' => 'forbidden', 'message' => 'IP not allowed'], 403);
        }
        return $next($request);
    }
}
