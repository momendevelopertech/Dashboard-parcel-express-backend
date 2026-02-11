<?php

namespace App\Observers;

 
use App\Models\Expense;
use App\Models\Hub;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseObserver
{
    public function creating(Expense $expense)
    {
        $user = Auth::user();

        if ($user) {
            if ($user->branch_user && $user->branch_user->branch) {
                $expense->owner()->associate($user->branch_user->branch);
            }

            if ($user->station_user && $user->station_user->station) {
                $expense->owner()->associate($user->station_user->station);
            }

            if ($user->hub_user && $user->hub_user->hub) {
                $expense->owner()->associate($user->hub_user->hub);
            }
        }
    }

    public function updating(Expense $expense)
    {
        $user = Auth::user();

        if ($user) {
            if ($user->branch_user && $branch = $user->branch_user->branch) {
                $expense->owner_type = Branch::class;
                $expense->owner_id = $branch->id;
            }

            if ($user->station_user && $station = $user->station_user->station) {
                $expense->owner_type = Station::class;
                $expense->owner_id = $station->id;
            }

            if ($user->hub_user && $hub = $user->hub_user->hub) {
                $expense->owner_type = Hub::class;
                $expense->owner_id = $hub->id;
            }
        }
    }
}
