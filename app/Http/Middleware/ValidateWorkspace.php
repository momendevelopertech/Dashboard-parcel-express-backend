<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;
use App\Models\{Hub, Station, Branch};

class ValidateWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Bypass for Drivers/Merchants
        if (!$user || $user->hasRole(['Driver', 'Merchant','SuperVisor'])) {
            return $next($request);
        }

        // Validate headers
        $workspaceKey  = $request->header('X-Workspace-Key');
        $workspaceType = $request->header('X-Workspace-Type'); // e.g., "App\Models\Station"

        // If headers are not provided, skip validation (some routes don't require workspaces)
        if (!$workspaceKey || !$workspaceType) {
            return $next($request);
        }

        // Allowed workspace types (full model class names)
        $allowedModels = [
            Hub::class,
            Station::class,
            Branch::class,
        ];

        // If headers are provided, they must be valid
        if (!in_array($workspaceType, $allowedModels, true)) {
            return $this->errorResponse('Invalid workspace type.', 422);
        }

        // Decrypt workspace key
        try {
            $decryptedKey = Crypt::decryptString($workspaceKey);
        } catch (\Exception $e) {
            return $this->errorResponse('Invalid workspace key format.');
        }

        // Eager load relationships
        $user->load(['hubs', 'stations', 'branches']);

        // Merge both workspace ID and type into request for use in scopes
        $request->merge([
            'selected_workspace' => $decryptedKey,
            'selected_workspace_type' => $workspaceType
        ]);

        return $next($request);
    }

    private function formatWorkspace($model): array
    {
        return [
            'id'   => Crypt::encryptString($model->id),
            'name' => $model->name,
            'type' => strtolower(class_basename($model)),
        ];
    }

    private function errorResponse($message, $code = 403, $data = []): Response
    {
        return response()->json(array_merge([
            'message' => $message,
            'code'    => $code,
        ], $data), $code);
    }
}
