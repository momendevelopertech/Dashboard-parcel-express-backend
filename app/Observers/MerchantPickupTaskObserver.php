<?php

namespace App\Observers;

use App\Models\MerchantPickupTask;
use App\Models\Branch;
use App\Models\Station;
use App\Models\Hub;
use Illuminate\Support\Facades\Auth;

class MerchantPickupTaskObserver
{
    /**
     * Handle the MerchantPickupTask "creating" event.
     *
     * @param  \App\Models\MerchantPickupTask  $task
     * @return void
     */
    public function creating(MerchantPickupTask $task)
    {
        $user = Auth::user();
        $selectedWorkspaceId = request('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                $task->owner_type = Branch::class;
                $task->owner_id = $selectedWorkspaceId;
            } elseif ($user->station_user) {
                $task->owner_type = Station::class;
                $task->owner_id = $selectedWorkspaceId;
            } elseif ($user->hub_user) {
                $task->owner_type = Hub::class;
                $task->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the MerchantPickupTask "updating" event.
     *
     * @param  \App\Models\MerchantPickupTask  $task
     * @return void
     */
    public function updating(MerchantPickupTask $task)
    {
        $user = Auth::user();
        $selectedWorkspaceId = request('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                $task->owner_type = Branch::class;
                $task->owner_id = $selectedWorkspaceId;
            } elseif ($user->station_user) {
                $task->owner_type = Station::class;
                $task->owner_id = $selectedWorkspaceId;
            } elseif ($user->hub_user) {
                $task->owner_type = Hub::class;
                $task->owner_id = $selectedWorkspaceId;
            }
        }
    }
}
