<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\ShipmentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="WMS",
 *     description="Warehouse Management System"
 * )
 */
class ReturnQueueController extends Controller
{
    /**
     * List return leg shipments ready for return dispatch
     * GET /api/v1/return-queue
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);

        $query = Shipment::with([
            'consignee.governorate',
            'consignee.state',
            'consignee.place',
            'merchant',
            'marketplacePartner',
            'shipment_information',
            'shipment_delivery',
        ])->where('direction', 'return_to_origin')
            ->whereHas('shipment_information', function ($q) {
                $q->where('in_warehouse', true);
            });

        $facility = facility();
        if ($facility) {
            $query->where('owner_type', $facility->type)
                ->where('owner_id', $facility->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', ShipmentStatusEnum::ORDER_SORTED);
        }

        if ($request->filled('return_kind')) {
            $query->where('return_kind', $request->input('return_kind'));
        }

        if ($request->filled('query')) {
            $query->where('tracking_no', 'like', '%' . $request->input('query') . '%');
        }

        $shipments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return sendResponse('Return queue shipments retrieved successfully.', $shipments);
    }
}
