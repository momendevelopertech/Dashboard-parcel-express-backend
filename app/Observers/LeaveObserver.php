<?php

namespace App\Observers;

use App\Models\Leave;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveObserver
{
    /**
     * Handle the Leave "created" event.
     */

     protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function creating(Leave $leave): void
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                
                $leave->owner_type = Branch::class;
                $leave->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                
                $leave->owner_type = Station::class;
                $leave->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $leave->owner_type = Hub::class;
                $leave->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the Leave "updated" event.
     */
    public function updated(Leave $leave): void
    {
        //
    }

    /**
     * Handle the Leave "deleted" event.
     */
    public function deleted(Leave $leave): void
    {
        //
    }

    /**
     * Handle the Leave "restored" event.
     */
    public function restored(Leave $leave): void
    {
        //
    }

    /**
     * Handle the Leave "force deleted" event.
     */
    public function forceDeleted(Leave $leave): void
    {
        //
    }
}
