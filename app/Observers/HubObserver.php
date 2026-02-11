<?php

namespace App\Observers;

use App\Models\AssignShipmentToShelf;
use App\Models\FacilityAccount;
use App\Models\Hub;
use App\Models\HubUser;
use App\Models\Shelf;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneShipment;

class HubObserver
{
    public function created(Hub $hub)
    {
        FacilityAccount::create([
            "owner_id" => $hub->id,
            "owner_type" => Hub::class,
        ]);

        $user = User::where('email', 'admin@gmail.com')->first();
        if ($user) {
            HubUser::create([
                "hub_id" => $hub->id,
                "user_id" => $user->id,
            ]);
        }
    }

    public function deleting(Hub $hub)
    {
        $hub->hub_users->each(function ($hubUser) {
            if ($hubUser->user && $hubUser->user->email !== 'admin@gmail.com') {
                $hubUser->user->delete();
            } else {
                $hubUser->delete();
            }
        });

        $hub->hub_users->delete();
    }

    public function deleted(Hub $hub)
    {
        info("HubObserver::deleted - Hub deletion completed - ID: " . $hub->id);
        User::where('owner_type', Hub::class)->where('owner_id', $hub->id)->delete();
        Shelf::where('owner_type', Hub::class)->where('owner_id', $hub->id)->delete();
        Zone::where('owner_type', Hub::class)->where('owner_id', $hub->id)->delete();
        ZoneShipment::where('owner_type', Hub::class)->where('owner_id', $hub->id)->delete();
        AssignShipmentToShelf::where('owner_type', Hub::class)->where('owner_id', $hub->id)->delete();
    }
}
