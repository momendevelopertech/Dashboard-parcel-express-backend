<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function creating(User $user): void
    {
        if (session()->has('skip_user_observer')) {
            return;
        }

        if (!$user->owner_id || !$user->owner_type) {
            $facility = facility();

            if ($facility) {
                $user->owner_id   ??= $facility->id;
                $user->owner_type ??= $facility->type;
            }
        }
    }


    /**
     * Handle the User "updated" event.
     */
    public function retreived(User $user): void
    {
        /* Log::info('Request Headers:', request()->headers->all()); */
        if (empty($user->owner_id) && request()->hasHeader('X-Workspace-Key')) {
            $user->owner_id = Crypt::decryptString(request()->header('X-Workspace-Key'));
            /* Log::info($user->owner_id); */
        }
        if (empty($user->owner_type) && request()->hasHeader('X-Workspace-Type')) {
            $user->owner_type = request()->header('X-Workspace-Type');
            /* Log::info($user->owner_type); */
        }
        /* Log::info($user->save); */
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
