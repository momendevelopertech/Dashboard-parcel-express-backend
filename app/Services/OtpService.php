<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsAppTemplate;

class OtpService
{
    public function generateAndSendOtp(User $user, $phone)
    {
        $otp = generate_otp();
        $expiresAt = Carbon::now()->addMinutes(2);
        $user->update([
            'verification_code' => $otp,
            'verification_code_expires_at' => $expiresAt,
            'verification_code_expires_at' => $expiresAt,
            'phone_verified_at' => null,
        ]);
        $whatsappService = new WhatsAppService();
        $template = WhatsAppTemplate::where('name', 'OTP_Verification')->first();
        $message = str_replace('{{otp}}', $otp, $template->message);
        $whatsappService->sendMessage($phone, $message);
        return $otp;
    }

    public function verifyOtp(User $user, $otp)
    {
        if ($user->verification_code !== $otp) {
            return false;
        }

        if (Carbon::now()->gt($user->verification_code_expires_at)) {
            return false;
        }

        $user->update([
            'phone_verified_at' => Carbon::now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
        ]);

        return true;
    }
    
}
