<?php

namespace App\Swagger\Schemas\Driver;

/**
 * @OA\Schema(
 *     title="HomepageStats",
 *     description="Driver app homepage statistics",
 *     @OA\Xml(name="HomepageStats")
 * )
 */
class HomepageStats
{
    /**
     * @OA\Property(
     *     title="Total Shipments",
     *     description="Total number of assigned shipments",
     *     example=25
     * )
     *
     * @var integer
     */
    public $total_shipments;

    /**
     * @OA\Property(
     *     title="Pending Shipments",
     *     description="Number of pending delivery shipments",
     *     example=8
     * )
     *
     * @var integer
     */
    public $pending_shipments;

    /**
     * @OA\Property(
     *     title="Completed Shipments",
     *     description="Number of completed shipments today",
     *     example=15
     * )
     *
     * @var integer
     */
    public $completed_shipments;

    /**
     * @OA\Property(
     *     title="COD Amount",
     *     description="Total cash on delivery amount collected",
     *     type="number",
     *     format="float",
     *     example=1250.75
     * )
     *
     * @var float
     */
    public $cod_amount;

    /**
     * @OA\Property(
     *     title="Pickup Tasks",
     *     description="Number of pending pickup tasks",
     *     example=3
     * )
     *
     * @var integer
     */
    public $pickup_tasks;

    /**
     * @OA\Property(
     *     title="Returned Shipments",
     *     description="Number of returned shipments",
     *     example=2
     * )
     *
     * @var integer
     */
    public $returned_shipments;

    /**
     * @OA\Property(
     *     title="Performance",
     *     description="Driver performance metrics",
     *     type="object",
     *     @OA\Property(property="delivery_rate", type="number", format="float", example=92.5),
     *     @OA\Property(property="average_time", type="string", example="12 minutes"),
     *     @OA\Property(property="rating", type="number", format="float", example=4.8)
     * )
     *
     * @var object
     */
    public $performance;
} 