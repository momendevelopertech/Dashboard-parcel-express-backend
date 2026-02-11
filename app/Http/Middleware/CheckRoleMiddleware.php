<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Get authenticated user using Sanctum guard
        $user = Auth::user();

        if (!$user) {
            return sendResponse("", [], false, ["Please login."], 401);
        }

        // Eager load roles to prevent N+1 queries
        $user->load('roles');

        // Check if user has any roles
        if ($user->roles->isEmpty()) {
            return sendResponse("", [], false, ["Access denied. No roles assigned."], 403);
        }

        // Convert both role sets to lowercase for case-insensitive comparison
        $userRoles = $user->roles->pluck('name')->map(fn($role) => strtolower($role))->toArray();
        $requiredRoles = array_map('strtolower', $roles);

        // Check for any matching role
        if (!array_intersect($userRoles, $requiredRoles)) {
            Log::error('Role check failed', [
                'user_id' => $user->id,
                'user_roles' => $userRoles,
                'required_roles' => $requiredRoles,
                'intersection' => array_intersect($userRoles, $requiredRoles)
            ]);
            return sendResponse("", [], false, ["You are not allowed to perform this action."], 403);
        }

        return $next($request);
    }
}
