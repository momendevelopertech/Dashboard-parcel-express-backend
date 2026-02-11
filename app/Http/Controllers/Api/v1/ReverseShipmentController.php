<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReverseShipmentController extends Controller
{
    /**
     * List reverse shipments (with filters)
     * GET /api/v1/reverse-shipments
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $query = ReverseShipment::with([
            'parentShipment',
            'sender',
            'receiver',
            'merchant',
            'senderAddress',
            'receiverAddress',
            'reversePickupRequest',
            'transactions'
        ])->where('status', '!=', "REVERSE_CANCELLED")->orderBy('created_at', 'desc');

        // Filter by merchant
        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->input('merchant_id'));
        }

        // Filter by status
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
        ;

        return $this->sendResponse($shipments, 'Reverse shipments retrieved successfully');
    }

    /**
     * Show single reverse shipment details
     * GET /api/v1/reverse-shipments/{id}
     */
    public function show(int $id): JsonResponse
    {
        $shipment = ReverseShipment::with([
            'consignee',
            'parentShipment',
            'merchant:id,name',
            'reversePickupRequest',
            'reversePickupShipment',
            'reversePickupShipment.reversePickupTask.driver:id,name',
            'shipmentHistories'
        ])->findOrFail($id);

        return $this->sendResponse($shipment, 'Reverse shipment retrieved successfully');
    }

    /**
     * Track reverse shipment by tracking number
     * GET /api/v1/reverse-shipments/track/{tracking_no}
     */
    public function track(string $tracking_no): JsonResponse
    {
        $shipment = ReverseShipment::with([
            'parentShipment',
            'sender',
            'receiver',
            'merchant',
            'senderAddress',
            'receiverAddress',
            'reversePickupRequest',
            'reversePickupShipments.reversePickupTask.driver',
            'transactions',
            'shipmentHistories'
        ])
            ->where('tracking_no', $tracking_no)
            ->firstOrFail();

        return $this->sendResponse($shipment, 'Reverse shipment tracked successfully');
    }

    /**
     * Get financial summary for reverse shipment
     * GET /api/v1/reverse-shipments/{id}/financial-summary
     */
    public function financialSummary(int $id): JsonResponse
    {
        $service = app(\App\Services\ReversePickup\ReversePickupFinancialService::class);

        try {
            $summary = $service->getFinancialSummary($id);
            return $this->sendResponse($summary, 'Financial summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to get financial summary: ' . $e->getMessage(), [], 500);
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
    /**
     * Print reverse shipment waybill
     */
    public function printReverseWaybill($tracking_no)
    {


        $shipment = ReverseShipment::with([
            "consignee",
            "consignee.city",
            "consignee.governorate",
            "consignee.state",
            "consignee.place",
            "sender",
            "senderAddress.country",
            "senderAddress.state",
            "receiverAddress.governorate",
            "receiverAddress.state",
            "receiverAddress.place",
            "parentShipment.shipment_information.zone",
            "parentShipment.shipment_items",
            "parentShipment.shipment_amounts",
            "merchant"
        ])->where('tracking_no', $tracking_no)->firstOrFail();

        $html = view('printReverseWaybill', ['shipment' => $shipment])->render();

        return response()->json(['html' => $html]);
    }

    public function canceleReverseShipment(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        // Query shipments table with is_return = true filter
        $query = Shipment::with([
            'merchant',
            'consignee',
            'returnRequest',
            'shipmentHistories'
        ])
            ->where('is_return', true)
            ->where(function ($q) {
                $q->where('status', 'CANCELLED')
                    ->orWhere('status', 'REVERSE_CANCELLED');
            })
            ->orderBy('created_at', 'desc');

        // Filter by merchant
        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->input('merchant_id'));
        }

        // Filter by tracking number
        if ($request->has('tracking_no')) {
            $query->where('tracking_no', 'like', '%' . $request->input('tracking_no') . '%');
        }

        // Filter by original tracking number (stored in return_request)
        if ($request->has('original_tracking_no')) {
            $query->whereHas('returnRequest', function ($q) use ($request) {
                $q->where('original_tracking_no', 'like', '%' . $request->input('original_tracking_no') . '%');
            });
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $shipments = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->sendResponse($shipments, 'Cancelled return shipments retrieved successfully');
    }

    /**
     * Update reverse shipment details
     * PUT/PATCH /api/v1/reverse-shipments/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $shipment = ReverseShipment::findOrFail($id);

        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'location_url' => 'nullable|url',
            'original_tracking_no' => 'nullable|string|max:255',
        ]);

        $shipment->update($validated);

        // Update linked Consignee (which represents the Customer in reverse flow usually)
        if ($shipment->consignee_id && $shipment->consignee) {
            $consigneeData = [];
            if ($request->filled('customer_name')) {
                $consigneeData['name'] = $request->customer_name;
            }
            if ($request->filled('customer_phone')) {
                $consigneeData['cellphone'] = $request->customer_phone;
            }
            if ($request->filled('customer_address')) {
                $consigneeData['streetAddress'] = $request->customer_address;
            }
            if ($request->filled('latitude')) {
                $consigneeData['latitude'] = $request->latitude;
            }
            if ($request->filled('longitude')) {
                $consigneeData['longitude'] = $request->longitude;
            }
            if ($request->filled('location_url')) {
                $consigneeData['location_url'] = $request->location_url;
            }

            if (!empty($consigneeData)) {
                $shipment->consignee->update($consigneeData);
            }
        }

        return $this->sendResponse($shipment->fresh(), 'Reverse shipment updated successfully');
    }
}
