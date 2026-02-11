<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\LowStockAlert;
use Illuminate\Http\Request;
use App\Notifications\StockLowNotification;
use Illuminate\Support\Facades\Notification;

/**
 * @OA\Tag(name="Other", description="Low Stock Alert Management")
 */
class LowStockAlertController extends Controller
{
    /**
     * @OA\Get(
     *     path="/low-stock-alerts",
     *     summary="Retrieve low stock alerts",
     *     description="Retrieves a list of low stock alerts.",
     *     tags={"Other"},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter alerts by status (active, resolved)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Low stock alerts retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index(Request $request)
    {
        $query = LowStockAlert::with('item');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $alerts = $query->orderBy('alert_date', 'desc')->paginate(15);
        return sendResponse(
            'Low stock alerts retrieved successfully',
            $alerts,
            true,
            [],
            200
        );
    }

    /**
     * @OA\Post(
     *     path="/low-stock-alerts",
     *     summary="Create a new low stock alert",
     *     description="Creates a new low stock alert.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="inventory_item_id", type="integer", description="ID of the inventory item"),
     *             @OA\Property(property="minimum_stock_level", type="integer", description="Minimum stock level"),
     *             @OA\Property(property="notification_methods", type="array", description="Notification methods (email, in_system, sms)", @OA\Items(type="string")),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Low stock alert created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'minimum_stock_level' => 'required|integer|min:0',
            'notification_methods' => 'required|array|min:1',
            'notification_methods.*' => 'in:email,in_system,sms',
        ]);
        $alert = LowStockAlert::create($data);
        Notification::route('mail', config('notifications.ops_email'))
            ->notify(new StockLowNotification($alert));
        return sendResponse(
            'Low stock alert created successfully',
            $alert,
            true,
            [],
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/low-stock-alerts",
     *     summary="Update a low stock alert",
     *     description="Updates a low stock alert.",
     *     tags={"Other"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", description="Status of the alert (active, resolved)"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Low stock alert updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'status' => 'required|in:active,resolved',
        ]);

        $alert = LowStockAlert::find($request->id);
        $alert->update($data);
        return sendResponse(
            'Low stock alert updated successfully',
            $alert,
            true,
            [],
            200
        );
    }
}
