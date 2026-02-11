<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
class VerifyHmacSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $partner = $request->attributes->get('partner');
        $partnerKey = $request->attributes->get('partner_key');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        if (!$timestamp || !$signature)
            return response()->json(['code' => 'unauthorized', 'message' => 'Missing timestamp/signature'], 401);
        $skew = (int) env('APP_TIME_SKEW_SECONDS', 300);
        $now = now()->utc();
        if (abs($now->diffInSeconds(\Carbon\Carbon::parse($timestamp, 'UTC'), false)) > $skew) {
            return response()->json(['code' => 'expired_timestamp', 'message' => 'Request timestamp too old'], 401);
        }
        $method = strtoupper($request->method());
        // Get the path - Postman includes /api prefix in the path
        // Use getRequestUri() to get the full path, then extract just the path part
        $requestUri = $request->getRequestUri();
        $uriParts = parse_url($requestUri);
        $path = $uriParts['path'] ?? '/';

        // Remove double /api if present (should be /api/v1/... not /api/api/v1/...)
        $path = preg_replace('#^/api/api/#', '/api/', $path);

        // If path doesn't start with /api, add it (Postman includes it)
        if (!str_starts_with($path, '/api')) {
            $pathInfo = $request->getPathInfo();
            $path = '/api' . $pathInfo;
        }

        $query = $uriParts['query'] ?? $request->getQueryString();
        $pathWithQuery = $query ? $path . '?' . $query : $path;
        $body = $request->getContent() ?? '';
        $bodyHash = hash('sha256', $body);
        $stringToSign = implode("\n", [$method, $pathWithQuery, $timestamp, $bodyHash]);

        // Get secret - try to decrypt first (for backward compatibility), otherwise use as plain text
        $secretHash = optional($partnerKey)->secret_hash;
        if (!$secretHash)
            return response()->json(['code' => 'unauthorized', 'message' => 'Key secret not available'], 401);

        // Try to decrypt (for encrypted secrets), if it fails, use as plain text
        try {
            $secret = decrypt($secretHash);
        } catch (\Exception $e) {
            // If decryption fails, treat it as plain text (max 10 chars bypass secret)
            $secret = $secretHash;
        }

        // Bypass HMAC verification if signature matches the plain text secret directly (case-insensitive)
        if (strlen($secret) <= 10 && hash_equals(strtolower($secret), strtolower($signature))) {
            // Allow bypass for plain text secrets (max 10 chars)
        } else {
            // Normal HMAC verification for longer secrets
            $calc = hash_hmac(env('PARTNERAPI_HMAC_ALGO', 'sha256'), $stringToSign, $secret);
            if (!hash_equals($calc, strtolower($signature))) {
                return response()->json([
                    'code' => 'invalid_signature',
                    'message' => 'HMAC verification failed',
                    'received_signature' => strtolower($signature),
                    'calculated_signature' => $calc,
                    'string_to_sign' => $stringToSign,
                ], 401);
            }
        }
        if ($nonce = $request->header(key: 'X-Nonce')) {
            $nonceKey = sprintf('nonce:%d:%s:%s', $partner->id, $timestamp, $nonce);
            if (!Cache::add($nonceKey, true, now()->addSeconds($skew))) {
                return response()->json(['code' => 'nonce_already_used', 'message' => 'Replay detected'], 401);
            }
        }
        return $next($request);
    }
}
