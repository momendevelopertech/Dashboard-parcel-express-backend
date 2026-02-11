<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
   
     protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function creating(Employee $employee): void
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                
                $employee->owner_type = Branch::class;
                $employee->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                
                $employee->owner_type = Station::class;
                $employee->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $employee->owner_type = Hub::class;
                $employee->owner_id = $selectedWorkspaceId;
            }
        }
    }
    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        //
    }
}
