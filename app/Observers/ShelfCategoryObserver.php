<?php

namespace App\Observers;

use App\Models\ShelfCategory;
use App\Models\Branch;
use App\Models\Hub;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShelfCategoryObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function creating(ShelfCategory $cat)
    {
        $facility = facility();

        if ($facility) {
            $cat->owner_type = $facility->type;
            $cat->owner_id = $facility->id;
        }
    }

    public function updating(ShelfCategory $cat)
    {
        $facility = facility();

        if ($facility) {
            $cat->owner_type = $facility->type;
            $cat->owner_id = $facility->id;
        }
    }
}
