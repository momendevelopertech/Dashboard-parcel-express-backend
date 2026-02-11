<?php

namespace App\Observers;

use App\Models\HierarchyLevel;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class HierarchyLevelObserver
{

    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }
    /**
     * Handle the HierarchyLevel "created" event.
     */
    public function creating(HierarchyLevel $hierarchyLevel): void
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                
                $hierarchyLevel->owner_type = Branch::class;
                $hierarchyLevel->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                
                $hierarchyLevel->owner_type = Station::class;
                $hierarchyLevel->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $hierarchyLevel->owner_type = Hub::class;
                $hierarchyLevel->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the HierarchyLevel "updated" event.
     */
    public function updated(HierarchyLevel $hierarchyLevel): void
    {
        //
    }

    /**
     * Handle the HierarchyLevel "deleted" event.
     */
    public function deleted(HierarchyLevel $hierarchyLevel): void
    {
        //
    }

    /**
     * Handle the HierarchyLevel "restored" event.
     */
    public function restored(HierarchyLevel $hierarchyLevel): void
    {
        //
    }

    /**
     * Handle the HierarchyLevel "force deleted" event.
     */
    public function forceDeleted(HierarchyLevel $hierarchyLevel): void
    {
        //
    }
}
