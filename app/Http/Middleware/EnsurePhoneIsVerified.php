<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePhoneIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && !$request->user()->phone_verified_at) {
            return response()->json([
                'message' => 'Your phone number is not verified.',
                'verification_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
