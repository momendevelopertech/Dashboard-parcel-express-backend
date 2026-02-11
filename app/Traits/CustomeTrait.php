<?php

namespace App\Traits;

use Carbon\Carbon;
use App\Models\Shipment;
use App\Models\Consignee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use App\Http\Resources\ConsigneeResource;

trait CustomeTrait
{
    protected static function storeConsignee($data)
    {

        try {
            if ($data['cellphone']) {
                $cellphoneSplit = splitPhoneNumber($data['cellphone']);
                $data['country_key_cellphone'] = $cellphoneSplit['country_code'];
                $data['cellphone'] = $cellphoneSplit['national_number'];
            }
            if ($data['alternatePhone']) {
                $alternatePhoneSplit = splitPhoneNumber($data['alternatePhone']);
                $data['country_key_alternatePhone'] = $alternatePhoneSplit['country_code'];
                $data['alternatePhone'] = $alternatePhoneSplit['national_number'];
            }
            $consignee = Consignee::create($data);
        } catch (QueryException $e) {
            return sendResponse("Error Occured.", [], [$e->getMessage()], 422);
        }
        return sendResponse("Consignee created successfully.", new ConsigneeResource($consignee));
    }


    /**
     * Get shipments with FUTURE_DELIVERY status
     */
    protected static function getFutureShipments()
    {
        $trackingNo = request()->query('query');
        $to = request()->query('to_future');
        $from = request()->query('from_future');
        $facilityId = request()->query('facility_id');
        $facilityType = request()->query('facility_type');
        $shelfBarcode = request()->query('shelf_barcode');
        $perPage = request()->query('per_page', 8);
        $hasOutsourcedParam = request()->has('is_outsourced');
        $isOutsourced = request()->boolean('is_outsourced');

        // Consignee filters
        $consigneeName = request()->query('consignee_name');
        $consigneePhone = request()->query('consignee_phone');
        $consigneeGovernorateId = request()->query('consignee_governorate_id');
        $consigneeStateId = request()->query('consignee_state_id');
        $consigneePlaceId = request()->query('consignee_place_id');

        // Created date filters
        $createdFrom = request()->query('created_from');
        $createdTo = request()->query('created_to');

        // Build base query
        if (has_role("Merchant")) {
            $shipmentsQuery = Shipment::query()
                ->where('merchant_id', Auth::id());
        } else {
            $shipmentsQuery = Shipment::query()->excludePickupUnassigned();
        }

        // Apply FUTURE_DELIVERY filter - includes both status and exception type
        $shipmentsQuery->where(function ($q) {
            $q->where('status', 'FUTURE_DELIVERY')
                ->orWhereHas('shipmentHistories', function ($qh) {
                    $qh->where('name', 'DELIVERY_EXCEPTION')
                        ->where('type', 'FUTURE_DELIVERY');
                });
        });

        // Outsourced filter
        if ($hasOutsourcedParam) {
            $shipmentsQuery->where('is_outsourced', $isOutsourced);
        }

        // Tracking number filter
        if ($trackingNo) {
            if (is_array($trackingNo)) {
                $shipmentsQuery->whereIn('tracking_no', $trackingNo);
            } else {
                $shipmentsQuery->where('tracking_no', 'like', '%' . $trackingNo . '%');
            }
        }

        // Future delivery date range filter
        if ($from && $to) {
            $shipmentsQuery->whereHas('shipment_delivery', function ($query) use ($from, $to) {
                $query->whereBetween('future_delivery_date', [$from, $to]);
            });
        }

        // Facility filters (can work independently)
        if ($facilityId) {
            $shipmentsQuery->where('owner_id', $facilityId);
        }

        if ($facilityType) {
            $shipmentsQuery->where('owner_type', $facilityType);
        }

        // Shelf barcode filter
        if ($shelfBarcode) {
            $shipmentsQuery->whereHas('assigned_to_shelf', function ($q) use ($shelfBarcode) {
                $q->where('barcode', $shelfBarcode);
            });
        }

        // Consignee filters
        if ($consigneeName) {
            $shipmentsQuery->whereHas('consignee', function ($q) use ($consigneeName) {
                $q->where('name', 'like', "%$consigneeName%");
            });
        }

        if ($consigneePhone) {
            $shipmentsQuery->whereHas('consignee', function ($q) use ($consigneePhone) {
                $q->where('cellphone', 'like', "%$consigneePhone%");
            });
        }

        if ($consigneeGovernorateId) {
            $shipmentsQuery->where('governorate_id', $consigneeGovernorateId);
        }

        if ($consigneeStateId) {
            $shipmentsQuery->where('state_id', $consigneeStateId);
        }

        if ($consigneePlaceId) {
            $shipmentsQuery->where('place_id', $consigneePlaceId);
        }

        // Warehouse filter
        if (request()->warehouse_id) {
            $shipmentsQuery->whereHas('destinationOwner', function ($query) {
                $query->where('name', request()->warehouse_id);
            });
        }

        // Created date filters
        if ($createdFrom) {
            try {
                $createdFromDateTime = Carbon::createFromFormat('Y-m-d H:i', str_replace('+', ' ', $createdFrom));
                $shipmentsQuery->where('created_at', '>=', $createdFromDateTime);
            } catch (\Exception $e) {
                // Handle invalid date format
            }
        }

        if ($createdTo) {
            try {
                $createdToDateTime = Carbon::createFromFormat('Y-m-d H:i', str_replace('+', ' ', $createdTo));
                $shipmentsQuery->where('created_at', '<=', $createdToDateTime);
            } catch (\Exception $e) {
                // Handle invalid date format
            }
        }

        $shipmentsQuery->with([
            'shipper:id,name,country_id,state_id,contact,zip_code,address',
            'shipper.country:id,name',
            'shipper.state:id,en_name,ar_name',
            'merchant:id,name',
            'merchant.merchant.country:id,name',
            'merchant.merchant.governorate:id,en_name,ar_name',
            'merchant.merchant.state:id,en_name,ar_name',
            'merchant.merchant.place:id,en_name,ar_name',
            'consignee:id,name,country_id,governorate_id,state_id,place_id,streetAddress,cellphone,alternatePhone,country_key_cellphone,country_key_alternatePhone,address_confirmed,longitude,latitude',
            'consignee.country:id,name',
            'consignee.governorate:id,en_name,ar_name',
            'consignee.state:id,en_name,ar_name',
            'consignee.place:id,en_name,ar_name',
            'consignee.old_address',
            'shipment_information:id,shipment_id,zone_id',
            'shipment_information.zone:id,name',
            'core_status',
            'shipmentHistories',
            'shipment_items',
            'shipment_delivery',
            'transactions',
            'governorate',
            'state',
            'place',
            'city',
            'assigned_to_shelf',
            'destinationOwner',
            'finalOwner',
            'currentOwner',
            'fromOwner',
        ]);

        $shipments = $shipmentsQuery->orderByDesc('id')->paginate($perPage);
        return $shipments;
    }


    protected static function getFutureDeliveryDueToday($date = null)
    {
        $targetDateTime =Carbon::now();

        return Shipment::where(function ($q) {
            $q->where('status', 'FUTURE_DELIVERY')
                ->orWhereHas('shipmentHistories', function ($qh) {
                    $qh->where('name', 'DELIVERY_EXCEPTION')
                        ->where('type', 'FUTURE_DELIVERY');
                });
        })
            ->whereHas('shipment_delivery', function ($query) use ($targetDateTime) {
                // Get shipments where future_delivery_date is now or in the past
                $query->where('future_delivery_date', '<=', $targetDateTime);
            })
            ->with(['consignee', 'shipment_delivery', 'assigned_to_shelf'])
            ->get();
    }
}
