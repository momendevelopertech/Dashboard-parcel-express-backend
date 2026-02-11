<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateScopes
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$required): Response
    {
        $partner = $request->attributes->get('partner');
        $scopes = collect(data_get($partner, 'allowed_scopes', []));

        foreach ($required as $scope) {
            if (!$scopes->contains($scope)) {
                return response()->json([
                    'code' => 'insufficient_scope',
                    'message' => 'Missing scope: ' . $scope,
                ], 403);
            }
        }

        return $next($request);
    }
}
