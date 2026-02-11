<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Partner;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PartnerController extends Controller
{
    public function test()
    {
        return response()->json([
            'message' => 'Partner Connected Successfully',
        ]);
    }

    public function me()
    {
        $partner = request()->attributes->get('partner');
        return response()->json([
            'partner_id' => 'PARTNER_' . $partner->id,
            'name' => $partner->name,
            'scopes' => $partner->allowed_scopes ?? [],
            'is_active' => $partner->is_active,
            'created_at' => $partner->created_at?->toIso8601String(),
        ]);
    }


    public function quota()
    {
        $partner = request()->attributes->get('partner');
        $rpm = (int) env('PARTNERAPI_RPM_DEFAULT', 120);
        $remaining = max(0, $rpm - 0);
        return response()->json([
            'partner_id' => 'PARTNER_' . $partner->id,
            'rate_limit' => [
                'requests_per_minute' => $rpm,
                'remaining' => $remaining,
                'reset_at' => now()->addMinute()->startOfMinute()->toIso8601String(),
            ],
        ]);
    }
}


