<?php

namespace App\Swagger\Schemas\Common;

/**
 * Security schemes for the API
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Laravel Sanctum token authentication. Use 'Bearer {your-token}' in the Authorization header."
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="workspace",
 *     type="apiKey",
 *     in="header",
 *     name="X-Workspace-Key",
 *     description="Encrypted workspace ID for multi-tenant access"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="workspaceType",
 *     type="apiKey",
 *     in="header",
 *     name="X-Workspace-Type",
 *     description="Workspace model class type (e.g., App\\Models\\Branch)"
 * )
 */
class SecuritySchemes
{
    // This class is only used for documentation purposes
} 