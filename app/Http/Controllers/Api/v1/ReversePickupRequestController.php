<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\ReversePickup\ReverseShipmentService;
use App\Models\ReversePickupRequest;
use App\Models\ReverseShipment;
use App\Models\ReversePickupRequestShipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;


class ReversePickupRequestController extends Controller
{
    public function __construct(
        private ReverseShipmentService $reverseShipmentService
    ) {}

    /**
     * List merchant's reverse pickup requests
     * GET /api/v1/reverse-pickup/requests
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
        $merchantAddress = $request->input('merchant_address');
        $ref = $request->input('ref');

        // Load reverseMerchantAccount with its location relations
        $requests = ReversePickupRequest::with([
            'merchant:id,name,phone',
            'merchant.reverseMerchantAccount.place',
            'merchant.reverseMerchantAccount.governorate',
            'merchant.reverseMerchantAccount.state'
        ])
            ->orderBy('created_at', 'desc')
            ->when($authUser->hasRole(['Merchant', 'merchant']), function ($query) use ($authUser) {
                // If the user is a merchant, show only their requests.
                // We use auth user's ID because referencing 'merchant_id' column on reverse_pickup_requests
                // likely corresponds to the user ID of the merchant.
                $query->where('merchant_id', $authUser->id);
            })
            ->when($warehouse_id && $accountableClasses, function ($query) use ($warehouse_id, $accountableClasses) {
                $query->where('owner_id', $warehouse_id)
                    ->where('owner_type', $accountableClasses);
            })
            ->where('status', '!=', 'cancelled')
            ->when($merchantId, function ($query) use ($merchantId) {
                $query->where('merchant_id', $merchantId);
            })
            ->when($merchantPhone, function ($query) use ($merchantPhone) {
                $query->whereHas('merchant', function ($q) use ($merchantPhone) {
                    $q->where('phone', 'like', "%{$merchantPhone}%");
                });
            })->when($ref, function ($query) use ($ref) {
                $query->where('ref', 'like', "%{$ref}%");
            })
            ->when($merchantAddress, function ($query) use ($merchantAddress) {
                // Search in reverseMerchantAccount fields (address column + relations)
                $query->whereHas('merchant.reverseMerchantAccount', function ($q2) use ($merchantAddress) {
                    $q2->where('address', 'like', "%{$merchantAddress}%")
                        ->orWhereHas('place', function ($q3) use ($merchantAddress) {
                            $q3->where('en_name', 'like', "%{$merchantAddress}%")
                                ->orWhere('ar_name', 'like', "%{$merchantAddress}%");
                        })
                        ->orWhereHas('governorate', function ($q3) use ($merchantAddress) {
                            $q3->where('en_name', 'like', "%{$merchantAddress}%")
                                ->orWhere('ar_name', 'like', "%{$merchantAddress}%");
                        })
                        ->orWhereHas('state', function ($q3) use ($merchantAddress) {
                            $q3->where('en_name', 'like', "%{$merchantAddress}%")
                                ->orWhere('ar_name', 'like', "%{$merchantAddress}%");
                        });
                });
            })
            ->paginate($perPage, ['*'], 'page', $page);

        $requests->getCollection()->transform(function ($item) {
            if ($item->merchant) {
                $profile = $item->merchant->reverseMerchantAccount;
                $item->merchant->setRelation('address', $profile);
                $item->merchant->unsetRelation('reverseMerchantAccount');
            }
            return $item;
        });

        return $this->sendResponse($requests, 'Reverse pickup requests retrieved successfully');
    }

    /**
     * Create a new reverse pickup request
     * POST /api/v1/reverse-pickup/requests
     */
    public function store(Request $request): JsonResponse
    {

        $authUser = Auth::user();
        if (!$authUser->hasRole('Merchant')) {
            return $this->sendError('you can not do this action');
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
            'scheduled_at' => 'nullable|date|after:now',
            'note' => 'nullable|string|max:1000',
        ]);

        // Calculate total shipments count from details
        $totalShipments = array_sum(array_column($validated['details'], 'count'));
        $validated['shipments_count'] = $totalShipments;

        // Add merchant ID from authenticated user
        $validated['merchant_id'] = $request->user()->id;

        // Add workspace ownership if available
        $user = $request->user();
        $merchant = $user->merchant;
        $validated['owner_id'] = $merchant->owner_id;
        $validated['owner_type'] = $merchant->owner_type;

        try {
            $reverseRequest = $this->reverseShipmentService->createReversePickupRequest($validated);

            return $this->sendResponse(
                $reverseRequest,
                "Reverse pickup request created successfully with {$totalShipments} shipment(s)",
                201
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to create reverse pickup request: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Show single reverse pickup request
     * GET /api/v1/reverse-pickup/requests/{id}
     */
public function show(int $id): JsonResponse
{
    $request = ReversePickupRequest::with([
        'originalShipment',
        'reverseShipments.transactions',
        'reverseShipments.reversePickupShipment', // 👈 ADD THIS
        'reverseTasks.driver',
        'customer',
        'merchant',
        'pickupAddress',
        'deliveryAddress',
        'reverseShipments.consignee',
    ])->findOrFail($id);

    return $this->sendResponse($request, 'Reverse pickup request retrieved successfully');
}


    /**
     * Cancel a reverse pickup request
     * POST /api/v1/reverse-pickup/requests/{id}/cancel
     */
    public function cancel(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $reverseRequest = ReversePickupRequest::findOrFail($id);

            // Cancel all associated reverse shipments
            foreach ($reverseRequest->reverseShipments as $reverseShipment) {
                $this->reverseShipmentService->cancelReverseShipment(
                    $reverseShipment->id,
                    $validated['reason'] ?? null
                );
            }

            // Update request status
            $reverseRequest->update([
                'status' => 'cancelled',
            ]);

            return $this->sendResponse($reverseRequest, 'Reverse pickup request cancelled successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to cancel reverse pickup request: ' . $e->getMessage(), [], 500);
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


    public function getRequestsForMerchant($merchantId): JsonResponse
    {

        $requests = ReversePickupRequest::where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->sendResponse($requests, 'Reverse pickup requests retrieved successfully');
    }
    public function reverseShipmetForAuthMerchant(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $merchantId = Auth::id();
        // 
        $query = ReverseShipment::where('merchant_id', $merchantId)->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by tracking number
        if ($request->has('tracking_no')) {
            $query->where('tracking_no', 'like', '%' . $request->input('tracking_no') . '%');
        }

        // Filter by original tracking number
        if ($request->has('original_tracking_no')) {
            $query->where('original_tracking_no', 'like', '%' . $request->input('original_tracking_no') . '%');
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $shipments = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->sendResponse($shipments, 'Reverse shipments retrieved successfully');
    }

    public function cancelReversePickupRequest(Request $request): JsonResponse
    {
        $facility = facility();
        $warehouse_id = $facility?->id;
        $accountableClasses = $facility?->type;

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        $authUser = Auth::user();
        $merchantId = $request->input('merchant_id');
        $merchantPhone = $request->input('merchant_phone');
        $merchantAddress = $request->input('merchant_address');
        $ref = $request->input('ref');

        // Load reverseMerchantAccount with its location relations
        $requests = ReversePickupRequest::with([
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
            })->when($ref, function ($query) use ($ref) {
                $query->where('ref', 'like', "%{$ref}%");
            })
            ->when($merchantAddress, function ($query) use ($merchantAddress) {
                // Search in reverseMerchantAccount fields (address column + relations)
                $query->whereHas('merchant.reverseMerchantAccount', function ($q2) use ($merchantAddress) {
                    $q2->where('address', 'like', "%{$merchantAddress}%")
                        ->orWhereHas('place', function ($q3) use ($merchantAddress) {
                            $q3->where('en_name', 'like', "%{$merchantAddress}%")
                                ->orWhere('ar_name', 'like', "%{$merchantAddress}%");
                        })
                        ->orWhereHas('governorate', function ($q3) use ($merchantAddress) {
                            $q3->where('en_name', 'like', "%{$merchantAddress}%")
                                ->orWhere('ar_name', 'like', "%{$merchantAddress}%");
                        })
                        ->orWhereHas('state', function ($q3) use ($merchantAddress) {
                            $q3->where('en_name', 'like', "%{$merchantAddress}%")
                                ->orWhere('ar_name', 'like', "%{$merchantAddress}%");
                        });
                });
            })
            ->paginate($perPage, ['*'], 'page', $page);

        $requests->getCollection()->transform(function ($item) {
            if ($item->merchant) {
                $profile = $item->merchant->reverseMerchantAccount;
                $item->merchant->setRelation('address', $profile);
                $item->merchant->unsetRelation('reverseMerchantAccount');
            }
            return $item;
        });

        return $this->sendResponse($requests, 'Reverse pickup requests retrieved successfully');
    }



    /**
     * Update the want_receive_at and is_hub_receive fields
     * PUT /api/v1/reverse-pickup/requests/{id}
     */
    public function addScenario(int $requestId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reverse_shipment_ids' => 'required|array|min:1',
            'reverse_shipment_ids.*' => 'required|exists:reverse_shipments,id',
            'want_receive_at' => 'nullable|date',
            'is_hub_receive' => 'nullable|boolean',
        ]);

        try {
            $scenario = ReversePickupRequestShipment::updateOrCreate(
                [
                    'reverse_pickup_request_id' => $requestId,
                    'reverse_shipment_ids' => $validated['reverse_shipment_ids'], // unique combination
                ],
                [
                    'want_receive_at' => $validated['want_receive_at'] ?? null,
                    'is_hub_receive' => $validated['is_hub_receive'] ?? false,
                ]
            );

            return $this->sendResponse(
                $scenario,
                'Scenario added/updated successfully'
            );
        } catch (\Exception $e) {
            return $this->sendError(
                'Failed to add scenario: ' . $e->getMessage(),
                [],
                500
            );
        }
    }


}
