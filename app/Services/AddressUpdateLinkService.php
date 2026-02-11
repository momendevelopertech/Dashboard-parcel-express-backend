<?php

namespace App\Services;

// app/Services/AddressUpdateLinkService.php

use App\Models\Shipment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AddressUpdateLinkService
{
    public function generate(Shipment $shipment): array
    {
        $consignee = $shipment->consignee ?? abort(422, 'No consignee');

        // توكن اللينك (plain في URL – هاش في DB)
        $plainToken = Str::random(40);
        $consignee->update_token = Hash::make($plainToken);
        $consignee->token_expires_at = Carbon::now()->addHours(24);

        // OTP 6 أرقام (plain يروح للمستخدم – هاش في DB)
        $otp = (string) random_int(100000, 999999);
        $consignee->address_update_otp = Hash::make($otp);
        $consignee->address_update_otp_expires_at = Carbon::now()->addMinutes(15);
        $consignee->address_update_verified_at = null; // reset
        $consignee->save();

        
        $marketingUrl = rtrim(config('app.marketing_url', env('MARKETING_APP_URL', 'https://parcelexpress.om')), '/');
        $url = "{$marketingUrl}/update-address/{$shipment->tracking_no}/{$plainToken}";

        return [
            'url' => $url,
            'otp' => $otp,
            'expires_at' => $consignee->address_update_otp_expires_at,
        ];
    }
}
