<?php

namespace App\Swagger\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="AuthResponseData",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(
 *         property="token",
 *         type="string",
 *         description="Personal access token"
 *     ),
 *     @OA\Property(
 *         property="workspace",
 *         ref="#/components/schemas/WorkspaceInfo"
 *     ),
 *     @OA\Property(
 *         property="settings",
 *         type="array",
 *         @OA\Items(type="object")
 *     ),
 *     @OA\Property(
 *         property="role",
 *         type="object",
 *         description="User role & permissions"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AuthResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Login successful."),
 *     @OA\Property(
 *         property="data",
 *         ref="#/components/schemas/AuthResponseData"
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="array",
 *         @OA\Items(type="string")
 *     )
 * )
 */
class Auth
{
}
