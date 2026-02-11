<?php

namespace App\Observers;

use App\Models\AssignShipmentToShelf;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\FacilityAccount;
use App\Models\Shelf;
use App\Models\Station;
use App\Models\StationUser;
use App\Models\TransferTaskShipment;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneShipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StationObserver
{

    // public function retrieved(Station $station)
    // {
    // }

    /**
     * Handle the Station "creating" event.
     *
     * @param  \App\Models\Station  $station
     * @return void
     */
    public function created(Station $station)
    {
        FacilityAccount::create([
            "owner_id" => $station->id,
            "owner_type" => Station::class,
        ]);

        $user = User::where('email', 'admin@gmail.com')->first();
        if ($user) {
            StationUser::create([
                "station_id" => $station->id,
                "user_id" => $user->id,
            ]);
        }
    }

    public function creating(Station $station)
    {
        $facility = facility();

        if ($facility) {
            $station->owner_type = $facility->type;
            $station->owner_id = $facility->id;
        }
    }

    /**
     * Handle the Station "updating" event.
     *
     * @param  \App\Models\Station  $station
     * @return void
     */
    public function updating(Station $station)
    {
        $facility = facility();

        if ($facility) {
            $station->owner_type = $facility->type;
            $station->owner_id = $facility->id;
        }
    }



    // /**
    //  * Handle the Station "deleting" event.
    //  *
    //  * @param  \App\Models\Station  $station
    //  * @return void
    //  */
    // public function deleting(Station $station)
    // {
    //     info("StationObserver::deleting - Station deletion started - ID: " . $station->id);

    //     // Handle station users deletion
    //     $station->station_users->each(function ($stationUser) {
    //         if ($stationUser->user && $stationUser->user->email !== 'admin@gmail.com') {
    //             info("Deleting user: " . $stationUser->user->email);
    //             $stationUser->user->delete();
    //         } else {
    //             info("Deleting station user record for admin");
    //             $stationUser->delete();
    //         }
    //     });

    //     info("Station users deletion completed for station ID: " . $station->id);

    //     // Get all zones owned by this station using the relationship
    //     $zones = $station->zones;

    //     info("Found " . $zones->count() . " zones to delete for station ID: " . $station->id);

    //     // Delete each zone individually to trigger any observers or events
    //     foreach ($zones as $zone) {
    //         info("Deleting zone ID: " . $zone->id . " - Name: " . $zone->name);
    //         $zone->delete();
    //     }

    //     info("All zones deleted for station ID: " . $station->id);
    // }

    // /**
    //  * Handle the Station "deleted" event.
    //  *
    //  * @param  \App\Models\Station  $station
    //  * @return void
    //  */
    public function deleted(Station $station)
    {
        info("StationObserver::deleted - Station deletion completed - ID: " . $station->id);
        User::where('owner_type', Station::class)->where('owner_id', $station->id)->delete();
        Shelf::where('owner_type', Station::class)->where('owner_id', $station->id)->delete();
        Zone::where('owner_type', Station::class)->where('owner_id', $station->id)->delete();
        ZoneShipment::where('owner_type', Station::class)->where('owner_id', $station->id)->delete();
        AssignShipmentToShelf::where('owner_type', Station::class)->where('owner_id', $station->id)->delete();
    }
}
