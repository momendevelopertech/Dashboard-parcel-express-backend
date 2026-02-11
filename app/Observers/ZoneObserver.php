<?php

namespace App\Observers;

use App\Models\Zone;
use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ZoneObserver
{
    /**
     * Handle the Zone "creating" event.
     *
     * @param  \App\Models\Zone  $zone
     * @return void
     */
    public function creating(Zone $zone)
    {
        if ($zone->owner_type && $zone->owner_id)
            return;

        if (function_exists('facility') && ($f = facility())) {
            $zone->owner_type ??= $f->type;
            $zone->owner_id ??= $f->id;
            return;
        }

        if (auth()->check()) {
            $u = auth()->user();
            $zone->owner_type ??= $u->owner_type;
            $zone->owner_id ??= $u->owner_id;
        }
    }

    /**
     * Handle the Zone "updating" event.
     *
     * @param  \App\Models\Zone  $zone
     * @return void
     */
    public function updating(Zone $zone)
    {
        // غالبًا لا تغيّر المالك أثناء التحديث
        // لو عايز تحتفظ بسياق facility عند غياب القيم:
        if (!$zone->owner_type || !$zone->owner_id) {
            if (function_exists('facility') && ($f = facility())) {
                $zone->owner_type ??= $f->type;
                $zone->owner_id ??= $f->id;
            }
        }
    }

    /**
     * Handle the Zone "deleting" event.
     *
     * @param  \App\Models\Zone  $zone
     * @return void
     */
    public function deleting(Zone $zone)
    {

    }

    /**
     * Handle the Zone "deleted" event.
     *
     * @param  \App\Models\Zone  $zone
     * @return void
     */
    public function deleted(Zone $zone)
    {
        // Log the zone deletion completion
        Log::info("Zone deletion completed", [
            'zone_id' => $zone->id,
            'zone_name' => $zone->name
        ]);
    }
}
