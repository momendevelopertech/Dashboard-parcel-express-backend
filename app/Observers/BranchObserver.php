<?php

namespace App\Observers;

use App\Models\Hub;
use App\Models\Branch;
use App\Models\BranchUser;
use App\Models\FacilityAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchObserver
{
    /**
     * Handle the Branch "creating" event.
     *
     * @param  \Spatie\Permission\Models\Branch  $branch
     * @return void
     */
    public function created(Branch $branch)
    {
        FacilityAccount::create([
            "owner_id" => $branch->id,
            "owner_type" => Branch::class,
        ]);

        $user = User::where('email', 'admin@gmail.com')->first();
        if ($user) {
            BranchUser::create([
                "branch_id" => $branch->id,
                "user_id" => $user->id,
            ]);
        }
    }


    public function creating(Branch $branch)
    {
        $facility = facility();

        if ($facility) {
            $branch->owner_type = $facility->type;
            $branch->owner_id = $facility->id;
        }
    }

    /**
     * Handle the Branch "updating" event.
     *
     * @param  \Spatie\Permission\Models\Branch  $branch
     * @return void
     */
    public function updating(Branch $branch)
    {
        $facility = facility();

        if ($facility) {
            $branch->owner_type = $facility->type;
            $branch->owner_id = $facility->id;
        }
    }

    public function deleting(Branch $branch)
    {
        $branch->branch_users->each(function ($branchUser) {
            if ($branchUser->user && $branchUser->user->email !== 'admin@gmail.com') {
                $branchUser->user->delete();
            } else {
                $branchUser->delete();
            }
        });

        $branch->branch_users->delete();
    }
}
