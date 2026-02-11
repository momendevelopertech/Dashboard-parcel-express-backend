<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\AdminCounterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationCounterController extends Controller
{
    protected $counterService;

    public function __construct(AdminCounterService $counterService)
    {
        $this->counterService = $counterService;
    }

    /**
     * Get the notification counts for unassigned, unregistered, and address revisions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Unassigned Shipments Count
        $unassignedCount = $this->counterService->getUnassignedCount($user);

        // 2. Unregistered Shipments Count
        $unregisteredCount = $this->counterService->getUnregisteredCount($user);

        // 3. New Counters
        $transferTaskCount = $this->counterService->getTransferTaskCount($user);
        $guestDriverCount = $this->counterService->getGuestDriverCount($user);
        $pickupRequests = $this->counterService->getPickupRequestsCount();
        $codCollection = $this->counterService->codCollection();
        $pickupCollection = $this->counterService->pickupCollection();



        // Address Revision (Legacy/Unused for now but kept as is)
        $addressRevisionCount = 0; 
        if (!$user->hasRole('Merchant')) {
             $addressRevisionCount = \App\Models\ShipmentAddressRevision::query()
                ->where('approved', false)
                ->where('rejected', false)
                ->whereHas('shipment', function($q) { $q->byOwner(); })
                ->count();
        }

        return response()->json([
            'unassigned_count' => $unassignedCount,
            'unregistered_count' => $unregisteredCount,
             'transfer_task_count' => $transferTaskCount,
            'guest_driver_count' => $guestDriverCount,
            'address_revision_count' => $addressRevisionCount,
            'pickup_requests_count' => $pickupRequests,
            'cod_collection' => $codCollection,
            'pickup_collection' => $pickupCollection,
        ]);
    }

    /**
     * Mark a specific notification type as seen.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function markSeen(Request $request)
    // {
    //     $request->validate([
    //         'type' => 'required|in:unassigned,unregistered,address_revision,transfer_task,guest_driver',
    //         'ids' => 'sometimes|array',
    //         'ids.*' => 'integer',
    //     ]);

    //     /** @var \App\Models\User $user */
    //     $user = Auth::user();
    //     $type = $request->input('type');
    //     $ids = $request->input('ids', []);

    //     if ($type === 'address_revision') {
    //         // Legacy handling or ignored for now as per prompt focus
    //         // existing used unassigned_last_seen_at which is distinct.
    //         // I will just return success.
    //         return response()->json(['message' => 'Marked as seen (Address Revision not fully implemented in new strict system)']);
    //     }

    //     $this->counterService->markAsSeen($user, $type, $ids);

    //     return response()->json([
    //         'message' => 'Marked as seen',
    //         'type' => $type,
    //         'timestamp' => now(),
    //     ]);
    // }
}
