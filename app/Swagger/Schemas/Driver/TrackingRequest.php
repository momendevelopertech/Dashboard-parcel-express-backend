<?php

namespace App\Swagger\Schemas\Driver;

/**
 * @OA\Schema(
 *     title="TrackingRequest",
 *     description="Request payload with tracking number",
 *     type="object",
 *     required={"tracking_no"},
 *     @OA\Xml(name="TrackingRequest")
 * )
 */
class TrackingRequest
{
    /**
     * @OA\Property(
     *     title="Tracking Number",
     *     description="Shipment tracking number",
     *     example="PE041225123456"
     * )
     *
     * @var string
     */
    public $tracking_no;
} 