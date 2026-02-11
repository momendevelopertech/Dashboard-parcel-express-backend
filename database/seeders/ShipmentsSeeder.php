<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Shipment;
use App\Models\Consignee;
use App\Models\ShipmentInformation;
use App\Models\ShipmentDelivery;
use App\Models\ShipmentFinance;
use App\Models\DriverShipmentAssignment;
use App\Models\DriverRunsheet;
use App\Models\DriverRunsheetShipment;
use App\Models\Account;
use App\Models\Transaction;

class ShipmentsSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {

            $consignee = Consignee::firstOrCreate(
                [
                    'cellphone' => '1206039762',
                    'country_key_cellphone' => '+20',
                    'country_id' => 165,
                ],
                [
                    'name' => 'Bulk Consignee',
                    'alternatePhone' => '96800000000',
                    'country_key_alternatePhone' => '+968',
                    'governorate_id' => 1,
                    'state_id' => 1,
                    'place_id' => 1,
                ]
            );

            $driverId = 2;
            $shipperId = 1;
            $merchantId = 5;
            $createdBy = 10;
            $countryId = 165;
            $governorateId = 1;
            $stateId = 1;
            $placeId = 1;

            $admin = User::where('email', 'superadmin@parcelexpress.com')->first();
            if ($admin) {
                Auth::login($admin);
            }

            // 4) Runsheet اليوم للسائق (pending)
            $driverRunsheet = DriverRunsheet::where('driver_id', $driverId)
                ->where('status', 'pending')
                ->whereDate('created_at', now()->toDateString())
                ->latest()
                ->first();

            if (!$driverRunsheet) {
                $driverRunsheet = DriverRunsheet::create([
                    'driver_id' => $driverId,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            for ($i = 1; $i <= 200; $i++) {

                $stamp = now()->addMilliseconds($i);
                $trackingNo = 'PE' . $stamp->format('ymdHis') . str_pad($i, 3, '0', STR_PAD_LEFT);

                $shipment = Shipment::create([
                    'owner_type' => 'App\Models\Hub',
                    'owner_id' => 1,
                    'consignee_id' => $consignee->id,
                    'shipper_id' => $shipperId,
                    'merchant_id' => $merchantId,
                    'driver_id' => null,
                    'shipment_type_id' => 1,
                    'tracking_no' => $trackingNo,

                    'value' => 0,
                    'delivery_fee' => 2.00,
                    'amount' => 2.00,
                    'payment_type' => 'Paid',
                    'notes' => 'Bulk seeded shipment',

                    'status' => 'SORT',
                    'is_return' => 0,
                    'in_exception' => 0,
                    'is_sorted' => 1,
                    'created_by' => $createdBy,

                    'country_id' => $countryId,
                    'governorate_id' => $governorateId,
                    'state_id' => $stateId,
                    'place_id' => $placeId,

                    'customer_name' => 'Test Customer',
                    'customer_phone' => '+201206039762',

                    'created_at' => $stamp,
                    'updated_at' => $stamp,
                ]);

                ShipmentInformation::create([
                    'shipment_id' => $shipment->id,
                    'merchant_id' => $merchantId,
                    'tracking_no' => $trackingNo,
                    'in_warehouse' => true,
                    'created_at' => $stamp,
                    'updated_at' => $stamp,
                ]);

                ShipmentDelivery::create([
                    'shipment_id' => $shipment->id,
                    'created_at' => $stamp,
                    'updated_at' => $stamp,
                ]);

                ShipmentFinance::create([
                    'shipment_tracking_no' => $trackingNo,
                    'created_at' => $stamp,
                    'updated_at' => $stamp,
                ]);

                if ($exist = DriverShipmentAssignment::where('shipment_id', $shipment->id)->first()) {
                    $exist->delete();
                }

                $assignment = new DriverShipmentAssignment();
                $assignment->shipment_id = $shipment->id;
                $assignment->shipment_tracking_no = $shipment->tracking_no;
                $assignment->driver_id = $driverId;
                $assignment->assigned_by = Auth::id();
                $assignment->assigned_at = $stamp;
                $assignment->save();

                $shipment->driver_id = $driverId;
                $shipment->assignment_id = $assignment->id;
                $shipment->is_sorted = false;
                $shipment->status = 'DISPATCH';    
                $shipment->updated_at = $stamp;
                $shipment->save();

              
                $od = $shipment->shipment_delivery;
                $od->ofd_count = ($od->ofd_count ?? 0) + 1;
                $od->updated_at = $stamp;
                $od->save();

              
                $statusLabel = status('DISPATCH')['label'] ?? 'DISPATCH';
                shipmentHistory([
                    'status' => $statusLabel,
                    'description' => "Shipment Dispatched by: " . (Auth::user()->name ?? 'system') .
                        " to: " . ($assignment->driver->name ?? 'driver#' . $driverId),
                    'shipment_id' => $shipment->id,
                ]);
                updateShipmentStatus($shipment->id, $statusLabel);

                DriverRunsheetShipment::firstOrCreate(
                    [
                        'runsheet_id' => $driverRunsheet->id,
                        'driver_id' => $driverId,
                        'shipment_tracking_no' => $shipment->tracking_no,
                    ],
                    [
                        'status' => 'assigned',
                        'created_at' => $stamp,
                        'updated_at' => $stamp,
                    ]
                );

                $transactionAmount = $shipment->amount ?? 0;
                $user = Auth::user();

                $facilityAccount = Account::firstOrCreate([
                    'accountable_id' => $user->owner_id,
                    'accountable_type' => $user->owner_type,
                ]);

                $driverAccount = Account::firstOrCreate([
                    'accountable_id' => $driverId,
                    'accountable_type' => User::class,
                ]);

                $facilityAccount->parcel_value -= $transactionAmount;
                $driverAccount->parcel_value += $transactionAmount;
                $facilityAccount->updated_at = $stamp;
                $driverAccount->updated_at = $stamp;
                $facilityAccount->save();
                $driverAccount->save();

                Transaction::create([
                    'from_id' => $user->owner_id,
                    'from_type' => $user->owner_type,
                    'to_id' => $driverId,
                    'to_type' => User::class,
                    'shipment_id' => $shipment->id,
                    'amount' => $transactionAmount,
                    'type' => 'assignment',
                    'created_at' => $stamp,
                    'updated_at' => $stamp,
                ]);

                $driverRunsheet->touch();
            }
        });
    }
}
