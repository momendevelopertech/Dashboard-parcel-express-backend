<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permission): Response
    {
        $user = Auth::user();

        if (!$user) {
            return sendResponse("", [], false,["Unauthorized."], 401);
        }

        if ($user->hasRole('Super Admin')) {
            return $next($request);
        }

        // Get permissions from user's role(s)
        $rolePermissions = $user->roles->flatMap->permissions ?? collect();

        // Get direct permissions assigned to the user
        $userPermissions = $user->permissions ?? collect();

        // Merge role permissions and direct user permissions
        $allPermissions = $rolePermissions->merge($userPermissions)->unique('id');

        // Handle OR conditions: split by '|' and check if user has any of the permissions
        $permissions = $allPermissions->pluck('name')->toArray();
        $hasPermission = false;

        foreach ($permissions as $perm) {
            if ($allPermissions->contains('name', $perm)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            return sendResponse("", [], false,["You don't have the permission to perform this action."], 422);
        }

        return $next($request);
    }
}
