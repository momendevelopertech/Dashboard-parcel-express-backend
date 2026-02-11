<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use App\Models\TransferTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransferTaskObserver
{
    protected $request;
    protected $adminCounterService;

    public function __construct(Request $request, \App\Services\AdminCounterService $adminCounterService)
    {
        $this->request = $request;
        $this->adminCounterService = $adminCounterService;
    }

    /**
     * Handle the TransferTask "creating" event.
     */
    public function creating(TransferTask $transferTask)
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {

                $transferTask->owner_type = Branch::class;
                $transferTask->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {

                $transferTask->owner_type = Station::class;
                $transferTask->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {

                $transferTask->owner_type = Hub::class;
                $transferTask->owner_id = $selectedWorkspaceId;
            }
        }
    }

    /**
     * Handle the TransferTask "created" event.
     */
    public function created(TransferTask $transferTask)
    {
        $origin = $transferTask->origin;
        if ($origin) {
            // Find users with 'Warehouse Supervisor' role associated with this origin
            // Check based on origin type to find relation
            $users = collect();

            if ($transferTask->origin_type === Branch::class) {
                $users = \App\Models\User::whereHas('branch_user', function ($q) use ($transferTask) {
                    $q->where('branch_id', $transferTask->origin_id);
                })->role('Warehouse Supervisor')->get();
            } elseif ($transferTask->origin_type === Station::class) {
                $users = \App\Models\User::whereHas('station_user', function ($q) use ($transferTask) {
                    $q->where('station_id', $transferTask->origin_id);
                })->role('Warehouse Supervisor')->get();
            } elseif ($transferTask->origin_type === Hub::class) {
                $users = \App\Models\User::whereHas('hub_user', function ($q) use ($transferTask) {
                    $q->where('hub_id', $transferTask->origin_id);
                })->role('Warehouse Supervisor')->get();
            }

            if ($users->count() > 0) {
                \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\TransferTaskCreatedNotification($transferTask));
            }
        }
        
        // Broadcast to admins for counters
        $this->adminCounterService->broadcastToAllAdmins();
    }

    /**
     * Handle the TransferTask "updating" event.
     */
    public function updating(TransferTask $transferTask)
    {
        $user = Auth::user();
        $selectedWorkspaceId = $this->request->get('selected_workspace');

        if ($user && $selectedWorkspaceId) {
            if ($user->branch_user) {
                $transferTask->owner_type = Branch::class;
                $transferTask->owner_id = $selectedWorkspaceId;
            }

            if ($user->station_user) {
                $transferTask->owner_type = Station::class;
                $transferTask->owner_id = $selectedWorkspaceId;
            }

            if ($user->hub_user) {
                $transferTask->owner_type = Hub::class;
                $transferTask->owner_id = $selectedWorkspaceId;
            }
        }
    }


    /**
     * Handle the TransferTask "updated" event.
     */
    public function updated(TransferTask $transferTask)
    {
         if ($transferTask->wasChanged('status')) {
             $this->adminCounterService->broadcastToAllAdmins();
         }
    }

}
