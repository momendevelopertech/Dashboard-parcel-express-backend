<?php

namespace App\Http\Middleware;

use App\Models\Driver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriverNotOnHold
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user(); // Sanctum/Session
        if (!$user)
            return $next($request);

        $driver = Driver::where('user_id', $user->id)->first();

        if ($driver && $driver->is_on_hold) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your driver account is on hold. Please contact support.'
                ], 423);
            }

            return redirect()->route('blocked')->with('error', 'Your driver account is on hold.');
        }

        return $next($request);
    }
}
