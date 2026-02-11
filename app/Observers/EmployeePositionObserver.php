<?php

namespace App\Observers;

use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\EmployeePosition;

class EmployeePositionObserver
{
    /**
     * Handle the EmployeePosition "created" event.
     */

     protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function creating(EmployeePosition $employeePosition): void
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                
                $employeePosition->owner_type = Branch::class;
                $employeePosition->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                
                $employeePosition->owner_type = Station::class;
                $employeePosition->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $employeePosition->owner_type = Hub::class;
                $employeePosition->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the EmployeePosition "updated" event.
     */
    public function updated(EmployeePosition $employeePosition): void
    {
        //
    }

    /**
     * Handle the EmployeePosition "deleted" event.
     */
    public function deleted(EmployeePosition $employeePosition): void
    {
        //
    }

    /**
     * Handle the EmployeePosition "restored" event.
     */
    public function restored(EmployeePosition $employeePosition): void
    {
        //
    }

    /**
     * Handle the EmployeePosition "force deleted" event.
     */
    public function forceDeleted(EmployeePosition $employeePosition): void
    {
        //
    }
}
