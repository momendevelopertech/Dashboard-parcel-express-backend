<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\ReturnRequest;
use App\Models\Shipment;
use App\Services\Return\ReturnShipmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * ReturnRequestController
 * 
 * Handles return pickup requests using unified architecture.
 * Replaces ReversePickupRequestController with simplified logic.
 * 
 * Key Changes:
 * - Uses ReturnRequest model (not ReversePickupRequest)
 * - Uses Shipment model with is_return=true (not ReverseShipment)
 * - Simplified relationships and queries
 */
class ReturnRequestController extends Controller
{
    public function __construct(
        private ReturnShipmentService $returnShipmentService
    ) {
    }

    /**
     * List merchant's return requests
     * GET /api/v1/return-requests
     */
    public function index(Request $request): JsonResponse
    {
        $facility = facility();
        $warehouse_id = $facility?->id;
        $accountableClasses = $facility?->type;

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $authUser = Auth::user();
        $merchantId = $request->input('merchant_id');
        $merchantPhone = $request->input('merchant_phone');
        $ref = $request->input('ref');
        $status = $request->input('status');

        $requests = ReturnRequest::with([
            'merchant:id,name,phone',
            'merchant.reverseMerchantAccount.place',
            'merchant.reverseMerchantAccount.governorate',
            'merchant.reverseMerchantAccount.state',
            'shipments' => function ($query) {
                $query->withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
                    ->select('id', 'tracking_no', 'status', 'return_request_id', 'driver_id','consignee_id')->with('consignee');
            },
        ])
            ->orderBy('created_at', 'desc')
            ->when($authUser->hasRole(['Merchant', 'merchant']), function ($query) use ($authUser) {
                $query->where('merchant_id', $authUser->id);
            })
            ->when($warehouse_id && $accountableClasses, function ($query) use ($warehouse_id, $accountableClasses) {
                $query->where('owner_id', $warehouse_id)
                    ->where('owner_type', $accountableClasses);
            })
            ->when($status, function ($query) use ($status) {
                $query->where('status', $status);
            }, function ($query) {
                // Default: exclude cancelled
                $query->where('status', '!=', 'cancelled');
            })
            ->when($merchantId, function ($query) use ($merchantId) {
                $query->where('merchant_id', $merchantId);
            })
            ->when($merchantPhone, function ($query) use ($merchantPhone) {
                $query->whereHas('merchant', function ($q) use ($merchantPhone) {
                    $q->where('phone', 'like', "%{$merchantPhone}%");
                });
            })
            ->when($ref, function ($query) use ($ref) {
                $query->where('ref', 'like', "%{$ref}%");
            })
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform merchant address relation
        $requests->getCollection()->transform(function ($item) {
            if ($item->merchant) {
                $profile = $item->merchant->reverseMerchantAccount;
                $item->merchant->setRelation('address', $profile);
                $item->merchant->unsetRelation('reverseMerchantAccount');
            }
            return $item;
        });

        return $this->sendResponse($requests, 'Return requests retrieved successfully');
    }

    /**
     * Create a new return pickup request
     * POST /api/v1/return-requests
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = Auth::user();
        if (!$authUser->hasRole('Merchant')) {
            return $this->sendError('You cannot perform this action', [], 403);
        }

        $oman = Country::where('name', 'Oman')->value('id');

        $validated = $request->validate([
            'details' => 'required|array|min:1',
            'details.*.original_tracking_no' => 'nullable|string|exists:shipments,tracking_no',
            'details.*.customer_name' => 'required|string|max:255',
            'details.*.customer_phone' => 'required|string|max:20',
            'details.*.customer_address' => 'nullable|string|max:1000',
            'details.*.count' => 'required|integer|min:1',
            'details.*.latitude' => 'nullable|numeric|between:-90,90',
            'details.*.longitude' => 'nullable|numeric|between:-180,180',
            'details.*.location_url' => 'nullable|url|max:500',
            'details.*.country_id' => 'required|integer|exists:countries,id',
            'details.*.governorate_id' => [
                Rule::requiredIf(fn() => $request->input('details.*.country_id') == $oman),
            ],
            'details.*.state_id' => 'required|integer|exists:states,id',
            'details.*.zone_id' => 'nullable|integer|exists:zones,id',
            'scheduled_at' => 'nullable|date|after:now',
            'note' => 'nullable|string|max:1000',
        ]);

        // Get merchant from authenticated user
        $merchant = $authUser->merchant;
        if (!$merchant) {
            return $this->sendError('Merchant profile not found for this user', [], 404);
        }

        $validated['merchant_id'] = $merchant->user_id;

        // Add workspace ownership if available
        $validated['owner_id'] = $merchant->owner_id ?? null;
        $validated['owner_type'] = $merchant->owner_type ?? null;

        try {
            $returnRequest = $this->returnShipmentService->createReturnPickupRequest($validated);

            return $this->sendResponse(
                $returnRequest,
                "Return request created successfully with {$returnRequest->no_of_shipments} shipment(s)",
                201
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to create return request: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Show single return request
     * GET /api/v1/return-requests/{id}
     */
    public function show(int $id): JsonResponse
    {
        $returnRequest = ReturnRequest::with([
            'shipments' => function ($query) {
                $query->with(['pickupTask.driver', 'consignee', 'transactions']);
            },
            'pickupTasks.driver',
            'customer',
            'merchant',
            'pickupAddress',
            'deliveryAddress',
        ])->findOrFail($id);

        return $this->sendResponse($returnRequest, 'Return request retrieved successfully');
    }

    /**
     * Cancel a return request
     * POST /api/v1/return-requests/{id}/cancel
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $returnRequest = ReturnRequest::findOrFail($id);

            // Cancel all associated shipments
            foreach ($returnRequest->shipments as $shipment) {
                $this->returnShipmentService->cancelReturnShipment(
                    $shipment->id,
                    $validated['reason'] ?? null
                );
            }

            // Update request status
            $returnRequest->update([
                'status' => 'cancelled',
            ]);

            return $this->sendResponse($returnRequest, 'Return request cancelled successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to cancel return request: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get return shipments for authenticated merchant
     * GET /api/v1/return-requests/shipments
     */
    public function getShipmentsForAuthMerchant(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $merchantId = Auth::id();

        $query = Shipment::withoutGlobalScope(\App\Models\Scopes\ExcludeReturnShipmentsScope::class)
            ->where('merchant_id', $merchantId)
            ->where('is_return', true)
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by tracking number
        if ($request->has('tracking_no')) {
            $query->where('tracking_no', 'like', '%' . $request->input('tracking_no') . '%');
        }

        // Filter by return type
        if ($request->has('return_type')) {
            $query->where('return_type', $request->input('return_type'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $shipments = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->sendResponse($shipments, 'Return shipments retrieved successfully');
    }

    /**
     * Get cancelled return requests
     * GET /api/v1/return-requests/cancelled
     */
    public function getCancelled(Request $request): JsonResponse
    {
        $facility = facility();
        $warehouse_id = $facility?->id;
        $accountableClasses = $facility?->type;

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $authUser = Auth::user();
        $merchantId = $request->input('merchant_id');
        $merchantPhone = $request->input('merchant_phone');
        $ref = $request->input('ref');

        $requests = ReturnRequest::with([
            'merchant:id,name,phone',
            'merchant.reverseMerchantAccount.place',
            'merchant.reverseMerchantAccount.governorate',
            'merchant.reverseMerchantAccount.state'
        ])
            ->orderBy('created_at', 'desc')
            ->when($authUser->hasRole(['Merchant', 'merchant']), function ($query) use ($authUser) {
                $query->where('merchant_id', $authUser->id);
            })
            ->when($warehouse_id && $accountableClasses, function ($query) use ($warehouse_id, $accountableClasses) {
                $query->where('owner_id', $warehouse_id)
                    ->where('owner_type', $accountableClasses);
            })
            ->where('status', 'cancelled')
            ->when($merchantId, function ($query) use ($merchantId) {
                $query->where('merchant_id', $merchantId);
            })
            ->when($merchantPhone, function ($query) use ($merchantPhone) {
                $query->whereHas('merchant', function ($q) use ($merchantPhone) {
                    $q->where('phone', 'like', "%{$merchantPhone}%");
                });
            })
            ->when($ref, function ($query) use ($ref) {
                $query->where('ref', 'like', "%{$ref}%");
            })
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform merchant address relation
        $requests->getCollection()->transform(function ($item) {
            if ($item->merchant) {
                $profile = $item->merchant->reverseMerchantAccount;
                $item->merchant->setRelation('address', $profile);
                $item->merchant->unsetRelation('reverseMerchantAccount');
            }
            return $item;
        });

        return $this->sendResponse($requests, 'Cancelled return requests retrieved successfully');
    }

    /**
     * Assign shipments to driver
     * POST /api/v1/return-requests/{id}/assign
     */
    public function assignToDriver(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipment_ids' => 'required|array|min:1',
            'shipment_ids.*' => 'required|integer|exists:shipments,id',
            'driver_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $tasks = $this->returnShipmentService->assignShipmentsToDriver(
                $validated['shipment_ids'],
                $validated['driver_id']
            );

            return $this->sendResponse(
                $tasks,
                count($tasks) . ' shipment(s) assigned to driver successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to assign shipments: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Assign shipments by zone
     * POST /api/v1/return-requests/{id}/assign-by-zone
     */
    public function assignByZone(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'required|integer|exists:zones,id',
            'driver_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $tasks = $this->returnShipmentService->assignShipmentsByZone(
                $id,
                $validated['zone_id'],
                $validated['driver_id']
            );

            return $this->sendResponse(
                $tasks,
                count($tasks) . ' shipment(s) in zone assigned to driver successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to assign shipments by zone: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Send response helper
     */
    protected function sendResponse($data, string $message, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Send error helper
     */
    protected function sendError(string $message, array $errors = [], int $code = 404): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
