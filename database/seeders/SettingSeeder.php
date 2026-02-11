<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Setting::create([
            'key' => 'facebook',
            'value' => "https://facebook.com",
            'type' => 'string',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'instagram',
            'value' => "https://instgram.com",
            'type' => 'string',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'url',
            'value' => "https://parcelexpress.om",
            'type' => 'string',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'driver_call_count',
            'value' => 3,
            'type' => 'integer',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'customer_contact_required',
            'value' => 1,
            'type' => 'boolean',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'default_merchant_commission',
            'value' => 1,
            'type' => 'integer',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'default_decimal_precision',
            'value' => 3,
            'type' => 'integer',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'default_shipper_commission',
            'value' => 1,
            'type' => 'integer',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'currency',
            'value' => '{"en": "OMR", "ar": "ر.ع"}',
            'type' => 'json',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'default_driver_delivery_bonuses',
            'value' => 0.600,
            'type' => 'integer',
            'status' => 'active',
        ]);

        Setting::create([
            'key' => 'default_driver_pickup_bonuses',
            'value' => 0.600,
            'type' => 'integer',
            'status' => 'active',
        ]);

        Setting::create([
            'description' => 'No need to cofirm the shipment after assignation.',
            'key' => 'driver_confirmation_required',
            'value' => 1,
            'type' => 'boolean',
            'status' => 'active',
        ]);

        Setting::create([
            'description' => 'Action for no answer parcels.',
            'key' => 'no_answer_action',
            'value' => 'move_to_dispatch',
            'type' => 'select',
            'status' => 'active',
        ]);

        Setting::create([
            'description' => 'Action for future parcels which are planned for tomorrow.',
            'key' => 'future_action_tomorrow',
            'value' => 'move_to_dispatch',
            'type' => 'select',
            'status' => 'active',
        ]);

        Setting::create([
            'description' => 'Action for future parcels which are planned for after tomorrow.',
            'key' => 'future_action_after',
            'value' => 'move_to_shelf',
            'type' => 'select',
            'status' => 'active',
        ]);

        Setting::create([
            'description' => 'Action for wrong city parcels.',
            'key' => 'wrong_city_action',
            'value' => 'move_to_supervisor',
            'type' => 'select',
            'status' => 'active',
        ]);

        Setting::create([
            'description' => 'By enable this action, we can send whatsapp messages after creating the shipment.',
            'key' => 'send_whatsapp_after_create_shipment',
            'value' => 'no',
            'type' => 'boolean',
            'status' => 'active',
        ]);

        Setting::create([
            'description' => 'By enable this action, whatsapp will be auto reply on whatsapp message that comes from anyone.',
            'key' => 'enable_autoreply_from_whatsapp',
            'value' => 'yes',
            'type' => 'boolean',
            'status' => 'active',
        ]);


        Setting::create([
            'description' => 'Action for OFD count.',
            'key' => 'ofd_count',
            'value' => 3,
            'type' => 'integer',
            'status' => 'active',
        ]);

        Setting::create([
            'description' => 'Show Financials to the Driver.',
            'key' => 'driver_show_financials',
            'value' => 3,
            'type' => 'boolean',
            'status' => 'active',
        ]);
    }
}
