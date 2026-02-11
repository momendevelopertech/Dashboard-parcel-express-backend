<?php

namespace App\Observers;

use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

class RoleObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the Role "creating" event.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @return void
     */
    public function creating(Role $role)
    {
        // $user = Auth::user();
        // $selectedWorkspaceId = $this->request->get('selected_workspace');

        // if ($user && $selectedWorkspaceId) {
        //     if ($user->branch_user) {
        //         $role->roleable_type = Branch::class;
        //         $role->roleable_id = $selectedWorkspaceId;
        //     }

        //     if ($user->station_user) {
        //         $role->roleable_type = Station::class;
        //         $role->roleable_id = $selectedWorkspaceId;
        //     }

        //     if ($user->hub_user) {
        //         $role->roleable_type = Hub::class;
        //         $role->roleable_id = $selectedWorkspaceId;
        //     }
        // }
    }

    /**
     * Handle the Role "updating" event.
     *
     * @param  \Spatie\Permission\Models\Role  $role
     * @return void
     */
    public function updating(Role $role)
    {
        // $user = Auth::user();
        // $selectedWorkspaceId = $this->request->get('selected_workspace');

        // if ($user && $selectedWorkspaceId) {
        //     if ($user->branch_user) {
        //         $role->roleable_type = Branch::class;
        //         $role->roleable_id = $selectedWorkspaceId;
        //     }

        //     if ($user->station_user) {
        //         $role->roleable_type = Station::class;
        //         $role->roleable_id = $selectedWorkspaceId;
        //     }

        //     if ($user->hub_user) {
        //         $role->roleable_type = Hub::class;
        //         $role->roleable_id = $selectedWorkspaceId;
        //     }
        // }
    }
}
