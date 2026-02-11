<?php

namespace App\Services;

use App\Models\MerchantWaybill;
use App\Models\Hub;
use App\Models\Shipment;
use App\Models\Consignee;
use App\Models\ShipmentDelivery;
use App\Models\ShipmentFinance;
use App\Models\ShipmentInformation;
use App\Models\ShipmentItem;
use App\Models\Station;
use App\Models\Account;
use App\Models\User;
use App\Models\MerchantCommission;
use App\Models\MerchantPickupTask;
use App\Models\MerchantPickupShipment;
use App\Models\Transaction;
use App\Models\Shipper;
use App\Models\Scopes\ConsigneeScope;
use App\Services\AddressService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class ShipmentService
{
    /**
     * Create a new shipment with its consignee
     *
     * @return Shipment
     */
    public function create_shipment($countryId = null, $governorateId = null, $stateId = null, $placeId = null): Shipment
    {
        return DB::transaction(function () use ($countryId, $governorateId, $stateId, $placeId) {
            $consignee = Consignee::create([
                'name' => 'Kinan Sleman',
                'email' => 'kksleman50@parcelexpress.com',
                'country_key_cellphone' => "+963",
                'cellphone' => '983843933',
                'country_key_alternatePhone' => '+963',
                'alternatePhone' => '983843933',
                'district' => null,
                'country_id' => $countryId,
                'governorate_id' => $governorateId,
                'state_id' => $stateId,
                'place_id' => $placeId,
                'zipcode' => 1,
                'streetAddress' => '12345',
                'identify' => null,
                'taxNumber' => null,
                'longitude' => '58.3431432921529',
                'latitude' => '23.590202440209573',
                'location_url' => null,
                'address_update_url' => null,
                'update_token' => '$2y$12$B/z/MqrZXvqL1nXVhEvkgetwge2G13mw8096m9fx7ZgoEW3TiGyBe',
                'token_expires_at' => null,
                'city_id' => null,
            ]);

            $shipment = Shipment::create([
                'facility_id' => null,
                'facility_type' => null,
                'owner_type' => Hub::class,
                'owner_id' => 1,
                "driver_id" => null,
                'consignee_id' => $consignee->id,
                'shipper_id' => 1,
                'merchant_id' => 5,
                "assignment_id" => null,
                'shipment_type_id' => null,
                'tracking_no' => generate_tracking_no(),
                'from_hub_id' => 1,
                'to_hub_id' => 1,
                'final_hub_id' => 1,
                'in_exception' => 0,
                'notes' => 1,
                'value' => 1,
                'delivery_fee' => 2,
                'amount' => 10,
                'is_return' => false,
                'created_by' => 1,
                'is_walkin' => 0,
                'is_outsourced' => 0,
                'customer_name' => 'Staff',
                'customer_phone' => '+923078529526',
                'payment_type' => 'COD',
                'status' => 'CREATED',
                'customer_id_card' => null,
                'customer_id' => null,
                'fee_payer' => 'customer',
                'country_id' => $countryId,
                'governorate_id' => $governorateId,
                'state_id' => $stateId,
                'place_id' => $placeId,
                'city_id' => null,
                'zipcode' => '12345',
                'streetAddress' => '54321',
                'longitude' => '58.3431432921529',
                'latitude' => '23.590202440209573',
                'allow_return' => 1,
                "delivery_priority" => 'normal',
                "delivery_time" => 'any',
                "sender_district" => null,
                "sender_location_url" => null,
                "sender_notes" => null,
                "sender_streetAddress" => null,
                "sender_zipcode" => null,
                "need_invoice" => 0,
                "sender_country_id" => null,
                "sender_governorate_id" => null,
                "sender_state_id" => null,
                "sender_place_id" => null,
            ]);

            ShipmentInformation::create([
                'shipment_id' => $shipment->id,
                'weight' => 10,
            ]);

            ShipmentDelivery::create([
                'shipment_id' => $shipment->id
            ]);

            ShipmentFinance::create([
                'shipment_tracking_no' => $shipment->tracking_no
            ]);

            $status = "ORDER_COLLECTED";

            $historyDataCreated = [
                "description" => status($status)['description'],
                "shipment_id" => $shipment->id,
                "name" => status($status)['label']
            ];

            shipmentHistory($historyDataCreated);

            return $shipment;
        });
    }

    /**
     * Create a merchant shipment with its consignee (mimics MerchantShipmentController store functionality)
     *
     * @param array $shipmentData
     * @param array $consigneeData
     * @param array $shipmentInformationData
     * @param array $shipmentItems
     * @return Shipment
     */
    public function create_merchant_shipment(
        array $shipmentData = [],
        array $consigneeData = [],
        array $shipmentInformationData = [],
        array $shipmentItems = []
    ): Shipment
    {
        return DB::transaction(function () use ($shipmentData, $consigneeData, $shipmentInformationData, $shipmentItems) {

            // Set default consignee data if not provided
            $defaultConsigneeData = [
                'name' => 'Test Customer',
                'email' => 'test@example.com',
                'cellphone' => '+923078529526',
                'alternatePhone' => '+923078529526',
                'country_id' => 165,
                'governorate_id' => 1,
                'state_id' => 1,
                'streetAddress' => 'Test Address',
                'district' => 'Test District',
                'zipcode' => '12345',
                'latitude' => null,
                'longitude' => null,
                'location_url' => null,
            ];

            $consigneeData = array_merge($defaultConsigneeData, $consigneeData);

            // If a Google Maps link is provided, attempt to extract coordinates
            if (!empty($consigneeData['location_url']) && (empty($consigneeData['latitude']) || empty($consigneeData['longitude']))) {
                try {
                    $addressService = new AddressService();
                    $parsed = $addressService->parseInputAddress($consigneeData['location_url']);
                    // Only override if coordinates were actually parsed
                    if ($parsed['latitude'] && $parsed['longitude']) {
                        $consigneeData['latitude'] = $parsed['latitude'];
                        $consigneeData['longitude'] = $parsed['longitude'];
                        if (empty($consigneeData['streetAddress'])) {
                            $consigneeData['streetAddress'] = $parsed['streetAddress'];
                        }
                    }
                } catch (Exception $e) {
                    // If parsing fails, continue without interrupting shipment creation
                }
            }

            // Check for existing consignee to avoid duplicates
            $existingConsignee = Consignee::withoutGlobalScope(ConsigneeScope::class)
                ->where('cellphone', $consigneeData['cellphone'])
                ->where('name', $consigneeData['name'])
                ->where('country_id', $consigneeData['country_id'])
                ->first();

            if ($existingConsignee) {
                $consignee = $existingConsignee;
            } else {
                $consignee = Consignee::create($consigneeData);
            }

            // Set default shipment data if not provided
            $defaultShipmentData = [
                'owner_type' => Station::class,
                'owner_id' => 3,
                'shipper_id' => Shipper::where("name", "Parcel Express")->first()?->id ?? 1,
                'merchant_id' => 8,
                'value' => 123,
                'payment_type' => 'COD',
                'status' => 'CREATED',
                'delivery_fee' => 2,
                'notes' => 'Test shipment created via service',
                'fee_payer' => 'customer',
                'created_by' => 8,
                'is_walkin' => 0,
            ];

            $shipmentData = array_merge($defaultShipmentData, $shipmentData);

            // Validate merchant commission for the destination state
            $merchant_commission = MerchantCommission::where('merchant_id', $shipmentData['merchant_id'])
                ->where('state_id', $consigneeData['state_id'])
                ->first();

            if (!$merchant_commission) {
                throw new Exception("Merchant commission not available for the selected state.");
            }

            // Set consignee and calculate amounts
            $shipmentData['consignee_id'] = $consignee->id;
            $shipmentData['delivery_fee'] = $merchant_commission->delivery_fee;
            
            // Use centralized calculation service for total_cod
            $calculationService = app(\App\Services\CalculationLogicService::class);
            $shipmentObj = (object) $shipmentData; // Convert to object for calculation
            $shipmentData['total_cod'] = $calculationService->getTotalCOD($shipmentObj);

            // Handle tracking number
            if (!isset($shipmentData['tracking_no'])) {
                $availableWaybill = MerchantWaybill::where('used', 0)->first();
                if ($availableWaybill) {
                    $shipmentData['tracking_no'] = $availableWaybill->tracking_no;
                } else {
                    $shipmentData['tracking_no'] = generate_tracking_no();
                }
            }

            // Create the shipment
            $shipment = Shipment::create($shipmentData);

            // Update merchant account with parcel value
            $merchantAccount = Account::firstOrCreate(
                [
                    'accountable_type' => User::class,
                    'accountable_id' => $shipment->merchant_id
                ],
                [
                    'parcel_value' => 0,
                    'balance' => 0
                ]
            );

            $merchantAccount->parcel_value += $shipmentData['total_cod'];
            $merchantAccount->save();

            // Mark merchant waybill as used if tracking number is from waybill
            $merchantWaybill = MerchantWaybill::where("tracking_no", $shipmentData['tracking_no'])->first();
            if ($merchantWaybill) {
                $merchantWaybill->used = 1;
                $merchantWaybill->save();
            }

            // Record transaction for merchant shipment creation
            Transaction::create([
                "to_id" => $shipmentData['merchant_id'],
                "to_type" => User::class,
                "shipment_id" => $shipment->id,
                "amount" => $shipment->total_cod,
                "type" => "merchant_created",
            ]);

            // Set default shipment information data
            $defaultShipmentInformationData = [
                'merchant_id' => $shipmentData['merchant_id'],
                'unit_id' => 1,
                'zone_id' => 1,
                'package_id' => 1,
                'weight' => 10,
                'height' => 10,
                'width' => 10,
                'length' => 10,
                'status' => 0,
            ];

            $shipmentInformationData = array_merge($defaultShipmentInformationData, $shipmentInformationData);

            // Create shipment information record
            ShipmentInformation::create([
                'shipment_id' => $shipment->id,
                'merchant_id' => $shipmentInformationData['merchant_id'],
                'unit_id' => $shipmentInformationData['unit_id'],
                'zone_id' => $shipmentInformationData['zone_id'],
                'package_id' => $shipmentInformationData['package_id'],
                'tracking_no' => $shipmentData['tracking_no'],
                'weight' => $shipmentInformationData['weight'],
                'height' => $shipmentInformationData['height'],
                'width' => $shipmentInformationData['width'],
                'length' => $shipmentInformationData['length'],
                'status' => $shipmentInformationData['status'],
            ]);

            // Create shipment delivery record
            ShipmentDelivery::create([
                'shipment_id' => $shipment->id
            ]);

            // Create shipment finance record
            ShipmentFinance::create([
                'shipment_tracking_no' => $shipment->tracking_no
            ]);

            // Create shipment items if provided
            if (!empty($shipmentItems)) {
                foreach ($shipmentItems as $item) {
                    if (isset($item['name']) && isset($item['quantity']) && isset($item['category'])) {
                        ShipmentItem::create([
                            'shipment_id' => $shipment->id,
                            'name' => $item['name'],
                            'quantity' => $item['quantity'],
                            'category' => $item['category'],
                        ]);
                    }
                }
            } else {
                // Create default shipment item for testing
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'name' => 'Test Item',
                    'quantity' => 1,
                    'category' => 'general',
                ]);
            }

            // Find an existing task with status 'created' - do NOT auto-create
            $task = MerchantPickupTask::where('merchant_id', $shipmentData['merchant_id'])
                ->where('status', 'created')
                ->first();

            // Only create MerchantPickupShipment if a valid task exists
            if ($task) {
                MerchantPickupShipment::create([
                    'pickup_task_id' => $task->id,
                    'merchant_id' => $shipmentData['merchant_id'],
                    'shipment_tracking_no' => $shipment->tracking_no,
                    'status' => 'created'
                ]);
            }

            // Create shipment collection history entry
            $status1 = "ORDER_COLLECTED";
            $timestamp = Carbon::now()->addSeconds(10)->toDateTimeString();

            $historyDataForCollected = [
                "description" => status($status1)['description'],
                "shipment_id" => $shipment->id,
                "status" => status($status1)['label'],
                "time" => $timestamp
            ];

            shipmentHistory($historyDataForCollected);

            // Log activity
            activityLog("merchant_shipment_created", "Merchant shipment created with tracking #{$shipment->tracking_no}");

            return $shipment;
        });
    }
}
