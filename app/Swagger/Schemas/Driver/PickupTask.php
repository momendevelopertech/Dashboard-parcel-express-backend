<?php

namespace App\Swagger\Schemas\Driver;

/**
 * @OA\Schema(
 *     title="PickupTask",
 *     description="Pickup task information for driver app",
 *     @OA\Xml(name="PickupTask")
 * )
 */
class PickupTask
{
    /**
     * @OA\Property(
     *     title="ID",
     *     description="Pickup task ID",
     *     format="int64",
     *     example=567
     * )
     *
     * @var integer
     */
    public $id;

    /**
     * @OA\Property(
     *     title="Merchant Name",
     *     description="Name of the merchant for pickup",
     *     example="ABC Electronics Store"
     * )
     *
     * @var string
     */
    public $merchant_name;

    /**
     * @OA\Property(
     *     title="Merchant Phone",
     *     description="Merchant contact phone number",
     *     example="+966501234567"
     * )
     *
     * @var string
     */
    public $merchant_phone;

    /**
     * @OA\Property(
     *     title="Address",
     *     description="Pickup address",
     *     example="Industrial City, Exit 10, Riyadh"
     * )
     *
     * @var string
     */
    public $address;

    /**
     * @OA\Property(
     *     title="Number of Packages",
     *     description="Expected number of packages to pickup",
     *     example=15
     * )
     *
     * @var integer
     */
    public $package_count;

    /**
     * @OA\Property(
     *     title="Status",
     *     description="Current pickup task status",
     *     example="ASSIGNED"
     * )
     *
     * @var string
     */
    public $status;

    /**
     * @OA\Property(
     *     title="Scheduled Time",
     *     description="Scheduled pickup time",
     *     example="2024-12-04 14:30:00"
     * )
     *
     * @var string
     */
    public $scheduled_time;

    /**
     * @OA\Property(
     *     title="Location",
     *     description="Pickup location coordinates",
     *     type="object",
     *     @OA\Property(property="latitude", type="number", format="float", example=24.7136),
     *     @OA\Property(property="longitude", type="number", format="float", example=46.6753)
     * )
     *
     * @var object
     */
    public $location;

    /**
     * @OA\Property(
     *     title="Notes",
     *     description="Special pickup instructions",
     *     example="Contact security at main gate"
     * )
     *
     * @var string
     */
    public $notes;

    /**
     * @OA\Property(
     *     title="Cached Shipment Step",
     *     description="Cached shipment step counter",
     *     format="int64",
     *     example=0
     * )
     *
     * @var integer
     */
    public $cached_shipment_step;
} 