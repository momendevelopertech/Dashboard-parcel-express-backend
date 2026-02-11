<?php

namespace App\Swagger\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="WorkspaceInfo",
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         description="Encrypted workspace ID"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Workspace name"
 *     ),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Workspace type (hub, station, branch)"
 *     )
 * )
 *
 *     )
 * )
 */
class Common
{
}
