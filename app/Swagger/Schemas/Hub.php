<?php

namespace App\Swagger\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Hub",
 *     type="object",
 *     description="Hub resource",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         example="Cairo Main Hub"
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         example="CAI-HUB"
 *     ),
 *     @OA\Property(
 *         property="country_id",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="governorate_id",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="state_id",
 *         type="integer",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="lat",
 *         type="number",
 *         format="float",
 *         example=30.0444
 *     ),
 *     @OA\Property(
 *         property="lng",
 *         type="number",
 *         format="float",
 *         example=31.2357
 *     ),
 *     @OA\Property(
 *         property="address",
 *         type="string",
 *         example="Tahrir Square, Downtown"
 *     ),
 *     @OA\Property(
 *         property="is_active",
 *         type="boolean",
 *         example=true
 *     )
 * )
 */
class Hub
{
}
