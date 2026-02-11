<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExcludeRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
public function handle(Request $request, Closure $next, ...$roles)
{
    $user = auth()->user();

    if (!$user) {
        return sendResponse("", [], false, ["Please login."], 401);
    }

    $user->load('roles');

    $userRoles = $user->roles
        ->pluck('name')
        ->map(fn ($r) => strtolower($r))
        ->toArray();

    $excludedRoles = array_map('strtolower', $roles);

    // ❌ If user has ANY excluded role → block
    if (array_intersect($userRoles, $excludedRoles)) {
        return sendResponse(
            "",
            [],
            false,
            ["You are not allowed to access this resource."],
            403
        );
    }

    return $next($request);
}

}
