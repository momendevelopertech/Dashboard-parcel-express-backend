<?php

namespace App\Observers;

use App\Models\EmployeeDepartment;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeDepartmentObserver
{

    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    /**
     * Handle the EmployeeDepartment "created" event.
     */
    public function creating(EmployeeDepartment $employeeDepartment): void
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                
                $employeeDepartment->owner_type = Branch::class;
                $employeeDepartment->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                
                $employeeDepartment->owner_type = Station::class;
                $employeeDepartment->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $employeeDepartment->owner_type = Hub::class;
                $employeeDepartment->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the EmployeeDepartment "updated" event.
     */
    public function updated(EmployeeDepartment $employeeDepartment): void
    {
        //
    }

    /**
     * Handle the EmployeeDepartment "deleted" event.
     */
    public function deleted(EmployeeDepartment $employeeDepartment): void
    {
        //
    }

    /**
     * Handle the EmployeeDepartment "restored" event.
     */
    public function restored(EmployeeDepartment $employeeDepartment): void
    {
        //
    }

    /**
     * Handle the EmployeeDepartment "force deleted" event.
     */
    public function forceDeleted(EmployeeDepartment $employeeDepartment): void
    {
        //
    }
}
