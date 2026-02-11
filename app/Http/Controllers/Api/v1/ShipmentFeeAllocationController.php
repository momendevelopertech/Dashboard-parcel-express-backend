<?php

namespace App\Http\Controllers\Api\v1;
use App\Http\Controllers\Controller;


use App\Models\Shipment;
use App\Models\ShipmentFeeAllocation;
use App\Models\ShipmentFeeAllocationOther;
use Illuminate\Http\Request;

class ShipmentFeeAllocationController extends Controller
{
    public function show(string $trackingNo)
    {
        $alloc = ShipmentFeeAllocation::where('shipment_tracking_no', $trackingNo)->first();

        // If not found → return zeros
        if (!$alloc) {
            return sendResponse('OK', [
                'rows' => [
                    [
                        'created_at' => now(),
                        'amount' => 0,
                        'recipient' => 'Driver (N/A)',
                        'type' => 'pickup_driver',
                        'shipment_no' => $trackingNo,
                    ],
                    [
                        'created_at' => now(),
                        'amount' => 0,
                        'recipient' => 'Warehouse (N/A)',
                        'type' => 'first_warehouse',
                        'shipment_no' => $trackingNo,
                    ],
                    [
                        'created_at' => now(),
                        'amount' => 0,
                        'recipient' => 'Warehouse (N/A)',
                        'type' => 'other_warehouse',
                        'shipment_no' => $trackingNo,
                    ],
                    [
                        'created_at' => now(),
                        'amount' => 0,
                        'recipient' => 'Driver (N/A)',
                        'type' => 'delivery_driver',
                        'shipment_no' => $trackingNo,
                    ],
                    [
                        'created_at' => now(),
                        'amount' => 0,
                        'recipient' => 'Parcel Express (N/A)',
                        'type' => 'company',
                        'shipment_no' => $trackingNo,
                    ],
                ]
            ]);
        }

        $createdAt = $alloc->created_at ?? $alloc->updated_at ?? now();
        $rows = [];

        $wh = fn($id) => $id ? "Warehouse #{$id}" : "Warehouse (N/A)";
        $dv = fn($id) => $id ? "Driver #{$id}" : "Driver (N/A)";
        $num = fn($v) => number_format((float) ($v ?? 0), 3, '.', '');

        // pickup
        $rows[] = [
            'created_at' => $createdAt,
            'amount' => $num($alloc->pickup_driver_amount),
            'recipient' => $dv($alloc->pickup_driver_id),
            'type' => 'pickup_driver',
            'shipment_no' => $trackingNo,
        ];

        // first
        $rows[] = [
            'created_at' => $createdAt,
            'amount' => $num($alloc->first_warehouse_amount),
            'recipient' => $wh($alloc->first_warehouse_id),
            'type' => 'first_warehouse',
            'shipment_no' => $trackingNo,
        ];

        // others (children preferred)
        $children = ShipmentFeeAllocationOther::where('shipment_fee_allocation_id', $alloc->id)->get();
        if ($children->count()) {
            foreach ($children as $c) {
                $rows[] = [
                    'created_at' => $createdAt,
                    'amount' => $num($c->amount),
                    'recipient' => $wh($c->warehouse_id),
                    'type' => 'other_warehouse',
                    'shipment_no' => $trackingNo,
                ];
            }
        } else {
            // single-other summary or 0
            $rows[] = [
                'created_at' => $createdAt,
                'amount' => $num($alloc->other_warehouse_amount),
                'recipient' => $wh($alloc->other_warehouse_id),
                'type' => 'other_warehouse',
                'shipment_no' => $trackingNo,
            ];
        }

        // delivery
        $rows[] = [
            'created_at' => $createdAt,
            'amount' => $num($alloc->delivery_driver_amount),
            'recipient' => $dv($alloc->delivery_driver_id),
            'type' => 'delivery_driver',
            'shipment_no' => $trackingNo,
        ];

        // company remainder
        $rows[] = [
            'created_at' => $createdAt,
            'amount' => $num($alloc->company_amount),
            'recipient' => 'Parcel Express (N/A)',
            'type' => 'company',
            'shipment_no' => $trackingNo,
        ];

        return sendResponse('OK', ['rows' => $rows]);
    }
}
