<?php

namespace App\Swagger\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Unit",
 *     type="object",
 *     description="Unit resource (real-estate or parcel unit)",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="project_id",
 *         type="integer",
 *         nullable=true,
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         example="Unit A-101"
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         example="A-101"
 *     ),
 *     @OA\Property(
 *         property="area",
 *         type="number",
 *         format="float",
 *         example=120.5
 *     ),
 *     @OA\Property(
 *         property="price",
 *         type="number",
 *         format="float",
 *         example=1500000
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         example="available"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         example="2025-12-03T12:00:00Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         example="2025-12-03T12:30:00Z"
 *     )
 * )
 */
class Unit
{
}
