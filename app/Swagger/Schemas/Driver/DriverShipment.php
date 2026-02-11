<?php

namespace App\Swagger\Schemas\Driver;

/**
 * @OA\Schema(
 *     title="DriverShipment",
 *     description="Shipment information for driver app",
 *     @OA\Xml(name="DriverShipment")
 * )
 */
class DriverShipment
{
    /**
     * @OA\Property(
     *     title="ID",
     *     description="Shipment ID",
     *     format="int64",
     *     example=1234
     * )
     *
     * @var integer
     */
    public $id;

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
     *     title="Status",
     *     description="Current shipment status",
     *     example="OUT_FOR_DELIVERY"
     * )
     *
     * @var string
     */
    public $status;

    /**
     * @OA\Property(
     *     title="Consignee",
     *     description="Recipient information",
     *     type="object",
     *     @OA\Property(property="name", type="string", example="Ahmed Hassan"),
     *     @OA\Property(property="phone", type="string", example="+966512345678"),
     *     @OA\Property(property="address", type="string", example="King Fahd Road, Riyadh"),
     *     @OA\Property(property="city", type="string", example="Riyadh"),
     *     @OA\Property(property="area", type="string", example="Al Olaya")
     * )
     *
     * @var object
     */
    public $consignee;

    /**
     * @OA\Property(
     *     title="COD Amount",
     *     description="Cash on delivery amount",
     *     type="number",
     *     format="float",
     *     example=250.50
     * )
     *
     * @var float
     */
    public $cod_amount;

    /**
     * @OA\Property(
     *     title="Payment Type",
     *     description="Payment method",
     *     example="COD"
     * )
     *
     * @var string
     */
    public $payment_type;

    /**
     * @OA\Property(
     *     title="Priority",
     *     description="Delivery priority",
     *     example="Normal"
     * )
     *
     * @var string
     */
    public $priority;

    /**
     * @OA\Property(
     *     title="Created At",
     *     description="Shipment creation timestamp",
     *     example="2024-12-04 10:30:00"
     * )
     *
     * @var string
     */
    public $created_at;

    /**
     * @OA\Property(
     *     title="Notes",
     *     description="Special delivery instructions",
     *     example="Please call before delivery"
     * )
     *
     * @var string
     */
    public $notes;

    /**
     * @OA\Property(
     *     title="Attempts",
     *     description="Number of delivery attempts",
     *     example=1
     * )
     *
     * @var integer
     */
    public $attempts;
} 