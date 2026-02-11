<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;


use App\Http\Resources\GeneralResource;
use App\Models\Notification;
use App\Traits\CustomeTrait;
use Illuminate\Http\Request;

use function Symfony\Component\Clock\now;

class AdminNotificationController extends Controller
{
    use CustomeTrait;
    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     summary="Get paginated list of notifications",
     *     tags={"Notifications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by notification status (read/unread)",
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
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=8)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 8);
        $userId = $request->user_id; // Get authenticated user ID

        // Base query for this user
        $baseQuery = Notification::query();
        $baseQuery->where('notifiable_id', $userId);

        // Apply status filter to base query
        if ($request->has('status')) {
            if ($request->status === 'unread') {
                $baseQuery->whereNull('read_at');
            } elseif ($request->status === 'read') {
                $baseQuery->whereNotNull('read_at');
            }
        }

        // Apply search filter to base query
        if ($request->has('search') && !empty($request->search)) {
            $baseQuery->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }

        // 1. Get chat notifications (for grouping)
        $chatQuery = clone $baseQuery;
        $latestChat = $chatQuery->where('type', 'new_merchant_message')
            ->orderBy('created_at', 'desc')
            ->first();

        $chatCount = (clone $baseQuery)->where('type', 'new_merchant_message')->whereNull('read_at')->count();

        // 2. Get non-chat notifications
        $otherQuery = clone $baseQuery;
        $otherNotifications = $otherQuery->where('type', '!=', 'new_merchant_message')
            ->orderBy('auto_id', 'desc')
            ->paginate($perPage);

        // 3. Build response data for GeneralResource
        $responseData = collect($otherNotifications->items());

        // Add grouped chat if exists
        if ($latestChat) {
            $responseData->push((object)[
                'id' => $latestChat->id,
                'type' => $latestChat->type,
                'title' => $latestChat->title,
                "notifiable_type" => $latestChat->notifiable_type,
                "notifiable_id" => $latestChat->notifiable_id,
                'content' => $latestChat->content,
                "auto_id" => $latestChat->auto_id,
                'read_at' => $latestChat->read_at,
                'data' => $latestChat->data,
                'created_at' => $latestChat->created_at,
                'chat_count' => $chatCount,
            ]);
        }

        // // Add other notifications
        // foreach ($otherNotifications->items() as $notification) {
        //     $responseData->push($notification);
        // }
        $responseData = $responseData
            ->sortByDesc('created_at')
            ->values();
        // Create a custom paginator with our merged data
        $customPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $responseData,
            $otherNotifications->total() + ($latestChat ? 1 : 0),
            $perPage,
            $otherNotifications->currentPage(),
            ['path' => $request->url()]
        );

        if ($responseData->isEmpty()) {
            return sendResponse("No notifications found.", [], false, ['No notifications found']);
        }

        return sendResponse("Notifications retrieved successfully.", new GeneralResource($customPaginator));
    }

    public function read($id)
    {
        try {
            $notification = Notification::findOrFail($id);
            if ($notification->type === 'new_merchant_message') {
                Notification::where('notifiable_id', $notification->notifiable_id)
                    ->where('type', 'new_merchant_message')->update(['read_at' => now()]);
                return sendResponse("Notification marked as read", $notification);
            }
            $notification->update(['read_at' => now()]);
            // $this->checkFutureShipment($notification);
            return sendResponse("Notification marked as read", $notification);
        } catch (\Exception $e) {
            return sendResponse("Notification not found", [], false, [$e->getMessage()], 404);
        }
    }


    public function readAll()
    {
        try {
            $updated = Notification::whereNull('read_at')->update(['read_at' => now()]);
            return sendResponse("All notifications marked as read", ['updated_count' => $updated]);
        } catch (\Exception $e) {
            return sendResponse("Error marking notifications as read", [], false, [$e->getMessage()], 500);
        }
    }

    public function stats(Request $request)
    {
        $userId = $request->query('user_id');

        $query = Notification::query();

        if ($userId) {
            $query->where('notifiable_id', $userId);
        }

        $total = $query->count();

        // Clone the query for unread count to maintain the same filters
        $unreadQuery = (clone $query)->whereNull('read_at');
        $unread = $unreadQuery->count();

        return sendResponse("Notification stats", [
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread
        ]);
    }
}
