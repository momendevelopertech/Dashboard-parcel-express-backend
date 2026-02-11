<?php

namespace App\Observers;

use App\Models\LeaveReason;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class LeaveReasonObserver
{
    /**
     * Handle the LeaveReason "created" event.
     */

    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function creating(LeaveReason $leaveReason): void
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {

                $leaveReason->owner_type = Branch::class;
                $leaveReason->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {

                $leaveReason->owner_type = Station::class;
                $leaveReason->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $leaveReason->owner_type = Hub::class;
                $leaveReason->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the LeaveReason "updated" event.
     */
    public function updated(LeaveReason $leaveReason): void
    {
        //
    }

    /**
     * Handle the LeaveReason "deleted" event.
     */
    public function deleted(LeaveReason $leaveReason): void
    {
        //
    }

    /**
     * Handle the LeaveReason "restored" event.
     */
    public function restored(LeaveReason $leaveReason): void
    {
        //
    }

    /**
     * Handle the LeaveReason "force deleted" event.
     */
    public function forceDeleted(LeaveReason $leaveReason): void
    {
        //
    }
}
