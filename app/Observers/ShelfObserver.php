<?php

namespace App\Observers;

use App\Models\Hub;
use App\Models\Shelf;
use App\Models\Branch;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShelfObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function creating(Shelf $shelf)
    {
        $facility = facility();

        if ($facility) {
            $shelf->owner_type = $facility->type;
            $shelf->owner_id = $facility->id;
        }
    }

    public function updating(Shelf $shelf)
    {
        $facility = facility();

        if ($facility) {
            $shelf->owner_type = $facility->type;
            $shelf->owner_id = $facility->id;
        }
    }
}
