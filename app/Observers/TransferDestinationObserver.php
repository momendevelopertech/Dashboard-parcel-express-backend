<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use App\Models\TransferDestination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransferDestinationObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the TransferDestination "creating" event.
     */
    public function creating(TransferDestination $transferDestination)
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {

                $transferDestination->owner_type = Branch::class;
                $transferDestination->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {

                $transferDestination->owner_type = Station::class;
                $transferDestination->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $transferDestination->owner_type = Hub::class;
                $transferDestination->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the TransferDestination "updating" event.
     */
    public function updating(TransferDestination $transferDestination)
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                $transferDestination->owner_type = Branch::class;
                $transferDestination->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                $transferDestination->owner_type = Station::class;
                $transferDestination->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {
                $transferDestination->owner_type = Hub::class;
                $transferDestination->owner_id = $selectedWorkspaceId;
            }
        }
    }
}
