<?php

namespace App\Models;

use App\Observers\ExpenseObserver;
use App\Traits\LogsUserActions;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[ObservedBy([ExpenseObserver::class])]
class Expense extends Model
{
    use HasFactory, LogsUserActions;

    protected $fillable = ['amount', 'name',"owner_id","owner_type"];

    public function owner()
    {
        return $this->morphTo();
    }

    public function scopeByOwner($query)
    {
        $user = Auth::user();

        if ($user) {
            if ($user->branch_user && $branch_id = $user->branch_user->branch_id) {
                $query->where('owner_type', Branch::class)
                    ->where('owner_id', $branch_id);
            }

            if ($user->station_user && $station_id = $user->station_user->station_id) {
                $query->where('owner_type', Station::class)
                    ->where('owner_id', $station_id);
            }

            if ($user->hub_user && $hub_id = $user->hub_user->hub_id) {
                $query->where('owner_type', Hub::class)
                    ->where('owner_id', $hub_id);
            }
        }
        return $query;
    }
}
