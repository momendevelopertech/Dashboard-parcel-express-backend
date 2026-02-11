<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Http\Resources\GeneralResource;
use App\Models\Consignee;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MerchantNotificationController extends Controller
{
    /**
     * Get Merchant Notifications
     *
     * @OA\Get(
     *     path="/merchant/notifications",
     *     summary="Get paginated list of merchant notifications",
     *     description="
     * Retrieve paginated list of notifications for authenticated merchant with filtering capabilities.
     * 
     * **Features:**
     * - Pagination support
     * - Status filtering (read/unread)
     * - Search functionality
     * - Sort by latest
     * 
     * **Security:**
     * - Merchant authentication required
     * - User-scoped notifications only
     * ",
     *     operationId="getMerchantNotifications",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by read status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"read", "unread"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in title or content",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notifications"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Notification::where('notifiable_id', Auth::id())
            ->where('notifiable_type', User::class)
            ->orderBy('id', 'desc');
        
        // Filter by read/unread status
        if ($request->has('status')) {
            if ($request->status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->status === 'read') {
                $query->whereNotNull('read_at');
            }
        }
        
        // Search by title or content
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }
        
        $notifications = $query->paginate(8);
        return sendResponse("Notifications", new GeneralResource($notifications));
    }

    /**
     * Mark Notification as Read
     *
     * @OA\Patch(
     *     path="/merchant/notifications/{id}/read",
     *     summary="Mark a notification as read",
     *     description="
     * Mark a specific notification as read for the authenticated merchant.
     * 
     * **Features:**
     * - Single notification marking
     * - Timestamp recording
     * - Ownership validation
     * - Error handling
     * 
     * **Security:**
     * - Merchant authentication required
     * - Notification ownership verified
     * ",
     *     operationId="markNotificationRead",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Notification ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notification marked as read"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notification not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function read($id)
    {
        try {
            $notification = Notification::where('notifiable_id', Auth::id())
                ->where('notifiable_type', User::class)
                ->findOrFail($id);

            $notification->update(['read_at' => now()]);
            return sendResponse("Notification marked as read", $notification);
        } catch (\Exception $e) {
            return sendResponse("Notification not found", [], false, [$e->getMessage()], 404);
        }
    }

    /**
     * Mark All Notifications as Read
     *
     * @OA\Patch(
     *     path="/merchant/notifications/read-all",
     *     summary="Mark all notifications as read",
     *     description="
     * Mark all unread notifications as read for the authenticated merchant.
     * 
     * **Features:**
     * - Bulk notification marking
     * - Update count tracking
     * - Atomic operation
     * - Performance optimized
     * 
     * **Security:**
     * - Merchant authentication required
     * - User-scoped operations only
     * ",
     *     operationId="markAllNotificationsRead",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All notifications marked as read"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function readAll()
    {
        try {
            $updated = Notification::where('notifiable_id', Auth::id())
                ->where('notifiable_type', User::class)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            return sendResponse("All notifications marked as read", ['updated_count' => $updated]);
        } catch (\Exception $e) {
            return sendResponse("Error marking notifications as read", [], false, [$e->getMessage()], 500);
        }
    }

    /**
     * Get Notification Statistics
     *
     * @OA\Get(
     *     path="/merchant/notifications/stats",
     *     summary="Get notification statistics",
     *     description="
     * Get statistical overview of notifications for the authenticated merchant.
     * 
     * **Features:**
     * - Total notifications count
     * - Unread notifications count
     * - Read notifications count
     * - Real-time statistics
     * 
     * **Security:**
     * - Merchant authentication required
     * - User-scoped data only
     * ",
     *     operationId="getNotificationStats",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notification statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notification stats"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="unread", type="integer", example=3),
     *                 @OA\Property(property="read", type="integer", example=22)
     *             ),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function stats()
    {
        $total = Notification::where('notifiable_id', Auth::id())
            ->where('notifiable_type', User::class)
            ->count();
        
        $unread = Notification::where('notifiable_id', Auth::id())
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->count();
        
        return sendResponse("Notification stats", [
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread
        ]);
    }

    /**
     * Get Customer Notifications
     *
     * @OA\Get(
     *     path="/merchant/notifications/customers",
     *     summary="Get notifications for merchant's customers",
     *     description="
     * Retrieve notifications sent to customers (consignees) associated with the authenticated merchant's shipments.
     * 
     * **Features:**
     * - Customer-scoped notifications
     * - Date range filtering
     * - Customer-specific filtering
     * - Search functionality
     * - Pagination support
     * 
     * **Security:**
     * - Merchant authentication required
     * - Only merchant's customers included
     * ",
     *     operationId="getCustomerNotifications",
     *     tags={"Merchant"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="Start date for filtering",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="End date for filtering",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="customer",
     *         in="query",
     *         description="Filter by specific customer ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in title or content",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer notifications retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer notifications"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="errors", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
    public function getCustomerNotifications(Request $request)
    {
        // Retrieve all consignee (customer) IDs that belong to the authenticated merchant
        $customerIds = Shipment::where('merchant_id', Auth::id())
            ->pluck('consignee_id');

        // Start the query on the Notification model and limit it to merchant's customers
        $query = Notification::whereIn('notifiable_id', $customerIds)
            ->where('notifiable_type', Consignee::class);

        // Date range filters
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        // Specific customer filter
        if ($request->filled('customer')) {
            $query->where('notifiable_id', $request->customer);
        }

        // Search in title / content
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Paginate results
        $notifications = $query->with('notifiable:name,cellphone')->orderBy('id', 'desc')->paginate(8);

        return sendResponse("Customer notifications", new GeneralResource($notifications));
    }
}