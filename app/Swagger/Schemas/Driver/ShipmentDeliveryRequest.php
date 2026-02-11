<?php

namespace App\Swagger\Schemas\Driver;

/**
 * @OA\Schema(
 *     title="ShipmentDeliveryRequest",
 *     description="Request payload for delivering an shipment",
 *     type="object",
 *     required={"tracking_no"},
 *     @OA\Xml(name="ShipmentDeliveryRequest")
 * )
 */
class ShipmentDeliveryRequest
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

    /**
     * @OA\Property(
     *     title="Delivery Proof",
     *     description="Proof of delivery image file",
     *     type="string",
     *     format="binary"
     * )
     *
     * @var string
     */
    public $proof;

    /**
     * @OA\Property(
     *     title="Recipient Name",
     *     description="Name of the person who received the package",
     *     example="Ahmed Hassan"
     * )
     *
     * @var string
     */
    public $recipient_name;

    /**
     * @OA\Property(
     *     title="Notes",
     *     description="Additional delivery notes",
     *     example="Delivered to the front door"
     * )
     *
     * @var string
     */
    public $notes;

    /**
     * @OA\Property(
     *     title="OTP",
     *     description="One-time password for delivery confirmation",
     *     example="123456"
     * )
     *
     * @var string
     */
    public $otp;

    /**
     * @OA\Property(
     *     title="Location",
     *     description="Delivery location coordinates",
     *     type="object",
     *     @OA\Property(property="latitude", type="number", format="float", example=24.7136),
     *     @OA\Property(property="longitude", type="number", format="float", example=46.6753)
     * )
     *
     * @var object
     */
    public $location;
} 