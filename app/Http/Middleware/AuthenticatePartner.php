<?php

namespace App\Http\Middleware;

use App\Models\PartnerKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePartner
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');
        if (!$apiKey)
            return response()->json(['code' => 'unauthorized', 'message' => 'Missing X-API-Key'], 401);
        $key = PartnerKey::with('partner')->where('key_id', $apiKey)->where('is_active', true)->first();
        if (!$key || !$key->partner || !$key->partner->is_active) {
            return response()->json(['code' => 'unauthorized', 'message' => 'Invalid or inactive key'], 401);
        }
        $request->attributes->set('partner', $key->partner);
        $request->attributes->set('partner_key', $key);
        return $next($request);
    }
}
