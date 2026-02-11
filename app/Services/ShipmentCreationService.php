<?php

namespace App\Services;

use App\Http\Controllers\SorterController;
use App\Models\Account;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Notifications\ShipmentCreatedNotification;
use Illuminate\Support\Facades\Auth;

class ShipmentCreationService
{
    public function finalizeAndPersist(array $shipmentData, $shouldSendWhatsapp = false, $role = null)
    {
        // 1) إنشاء الأوردر
        /** @var Shipment $shipment */
        $shipment = Shipment::create($shipmentData)->load('consignee');

        // 2) Set hub information using the new service
        $hubService = app(\App\Services\HubInformationService::class);
        
        // Set final hub (ultimate destination from zone - IMMUTABLE)
        $hubService->setFinalHub($shipment);
        
        // Set initial current hub (where shipment is created)
        $hubService->setInitialCurrentHub($shipment);
        
        $shipment->save();

        if ((int) ($shipment->is_outsourced ?? 0) !== 1 && $shouldSendWhatsapp) {
            $link = app(\App\Services\AddressUpdateLinkService::class)->generate($shipment);
            $shipment->consignee->notify(new ShipmentCreatedNotification($shipment, $link['url'], $link['otp']));
        }

        $ownerAccount = Account::firstOrCreate(
            ['accountable_type' => Auth::user()->owner_type, 'accountable_id' => Auth::user()->owner_id],
            ['parcel_value' => 0, 'balance' => 0]
        );
        $ownerAccount->parcel_value += $shipmentData['total_cod'] ?? 0;
        $ownerAccount->save();

        Transaction::create([
            "from_id" => $shipment->merchant ? $shipment->merchant->id : null,
            "from_type" => \App\Models\User::class,
            "to_id" => Auth::user()->owner_id,
            "to_type" => Auth::user()->owner_type,
            "shipment_id" => $shipment->id,
            "amount" => $shipment->total_cod ?? 0,
            "type" => "merchant_created",
        ]);

        return $shipment;
    }
}