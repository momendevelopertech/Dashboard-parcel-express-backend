<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use Illuminate\Http\Request;

class PartnerOrderController extends Controller
{
    public function showByInternalId(string $id)
    {
        $partner = request()->attributes->get('partner');

        $shipment = Shipment::query()
            ->where('id', $id)
            ->where('partner_id', $partner->id)
            ->with([
                'consignee.country',
                'consignee.state',
                'consignee.governorate',
                'consignee.place',
                // add any relations you already rely on
            ])
            ->first();

        if (!$shipment) {
            return response()->json([
                'code' => 'resource_not_found',
                'message' => 'Shipment not found',
                'details' => ['shipment_id' => $id],
            ], 404);
        }


        return response()->json($this->transformShipmentForPartner($shipment));
    }

    // OPTIONAL: GET /v1/shipments/by-tracking/{tracking_no}
    // If you want to keep parity with your existing show($tracking_no)
    public function showByTracking(string $trackingNo)
    {
        $partner = request()->attributes->get('partner');

        $shipment = Shipment::query()
            ->where('tracking_no', $trackingNo)
            ->where('partner_id', $partner->id)
            ->with([
                'consignee.country',
                'consignee.state',
                'consignee.governorate',
                'consignee.place',
            ])
            ->first();

        if (!$shipment) {
            return response()->json([
                'code' => 'resource_not_found',
                'message' => 'Shipment not found',
                'details' => ['tracking_no' => $trackingNo],
            ], 404);
        }

        return response()->json($this->transformShipmentForPartner($shipment));
    }

    // GET /v1/shipments?status=&from=&to=&partner_shipment_id=&limit=&page=
    public function index(Request $request)
    {
        $partner = $request->attributes->get('partner');

        $limit = min((int) $request->query('limit', 50), 200);
        $page = max((int) $request->query('page', 1), 1);

        $q = Shipment::query()
            ->where('partner_id', $partner->id);

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($pid = $request->query('partner_shipment_id')) {
            $q->where('partner_shipment_id', $pid);
        }
        if ($from = $request->query('from')) {
            $q->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->where('created_at', '<=', $to);
        }

        // Eager-load what you need for transformation
        $q->with([
            'consignee.country',
            'consignee.state',
            'consignee.governorate',
            'consignee.place',
        ]);

        $total = (clone $q)->count();
        $rows = $q->orderByDesc('id')->forPage($page, $limit)->get();

        $data = $rows->map(fn($shipment) => $this->transformShipmentForPartner($shipment));

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'total_pages' => (int) ceil($total / $limit),
                'total_records' => $total,
                'per_page' => $limit,
            ],
        ]);
    }

    /**
     * Map your rich Shipment model to the partner response shape.
     * Adjust the field names below to your actual columns.
     */
    private function transformShipmentForPartner($shipment): array
    {
        // Tracking numbers: adapt to your table/relationship
        $trackingNumbers = [];
        if (!empty($shipment->tracking_no)) {
            $trackingNumbers[] = (string) $shipment->tracking_no;
        }

        // Recipient mapping (using your consignee relations)
        $recipient = [
            'name' => $shipment->consignee->name ?? null,
            'phone' => $shipment->consignee->cellphone ?? null,
            'address' => [
                'line1' => $shipment->deliveryAddress->streetAddress ?? ($shipment->consignee->address ?? null),
                'city' => optional($shipment->deliveryAddress->city)->name
                    ?? optional($shipment->consignee->place)->en_name
                    ?? optional($shipment->consignee->state)->en_name
                    ?? null,
                'postal_code' => $shipment->deliveryAddress->zipcode ?? ($shipment->consignee->zipcode ?? null),
                'country' => $shipment->deliveryAddress->country->iso2 ?? ($shipment->consignee->country->iso2 ?? 'OM'),
            ],
        ];

        // Items: if you have shipment_items relation, map it; else an empty list
        $items = collect($shipment->shipment_items ?? [])->map(function ($it) {
            return [
                'sku' => $it->sku ?? null,
                'description' => $it->description ?? ($it->name ?? null),
                'quantity' => (int) ($it->quantity ?? 1),
                'weight_kg' => isset($it->weight_grams)
                    ? round(((float) $it->weight_grams) / 1000, 3)
                    : (float) ($it->weight_kg ?? 0),
            ];
        })->values()->all();

        // Shipping summary: adjust to your schema
        $shipping = [
            'service_code' => $shipment->service_code ?? 'EXPRESS',
            'weight_kg' => (float) ($shipment->weight_kg ?? 0),
            'estimated_delivery' => optional($shipment->eta_at)->toIso8601String(),
        ];

        return [
            'internal_shipment_id' => (string) $shipment->id,
            'partner_shipment_id' => $shipment->partner_shipment_id ?? null,
            'status' => $shipment->status ?? 'created',
            'tracking_numbers' => $trackingNumbers,
            'created_at' => optional($shipment->created_at)->toIso8601String(),
            'recipient' => $recipient,
            'items' => $items,
            'shipping' => $shipping,
        ];
    }
}
