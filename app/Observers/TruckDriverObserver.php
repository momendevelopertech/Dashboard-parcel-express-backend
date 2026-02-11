<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use App\Models\TruckDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TruckDriverObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the TruckDriver "creating" event.
     */
    public function creating(TruckDriver $truckDriver)
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {

                $truckDriver->owner_type = Branch::class;
                $truckDriver->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {

                $truckDriver->owner_type = Station::class;
                $truckDriver->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $truckDriver->owner_type = Hub::class;
                $truckDriver->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the TruckDriver "updating" event.
     */
    public function updating(TruckDriver $truckDriver)
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                $truckDriver->owner_type = Branch::class;
                $truckDriver->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                $truckDriver->owner_type = Station::class;
                $truckDriver->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {
                $truckDriver->owner_type = Hub::class;
                $truckDriver->owner_id = $selectedWorkspaceId;
            }
        }
    }
}
