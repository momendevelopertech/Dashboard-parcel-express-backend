<?php

namespace App\Swagger\Schemas;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Shipment",
 *     type="object",
 *     description="Shipment resource",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=1001
 *     ),
 *     @OA\Property(
 *         property="tracking_no",
 *         type="string",
 *         example="PE301125491431"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         example="DISPATCH"
 *     ),
 *     @OA\Property(
 *         property="shipment_type_id",
 *         type="integer",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="value",
 *         type="number",
 *         format="float",
 *         example=0
 *     ),
 *     @OA\Property(
 *         property="total_cod",
 *         type="number",
 *         format="float",
 *         example=150.00
 *     ),
 *     @OA\Property(
 *         property="delivery_fee",
 *         type="number",
 *         format="float",
 *         example=30.00
 *     ),
 *     @OA\Property(
 *         property="payment_type",
 *         type="string",
 *         example="Paid"
 *     ),
 *     @OA\Property(
 *         property="delivery_priority",
 *         type="string",
 *         example="normal"
 *     ),
 *     @OA\Property(
 *         property="delivery_time",
 *         type="string",
 *         example="any"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         example="2025-11-30T12:30:00Z"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         example="2025-11-30T14:00:00Z"
 *     )
 * )
 */
class Shipment
{
}
