<?php

namespace App\Observers;

use App\Models\StockOutTask;
use Illuminate\Http\Request;

class StockOutTaskObserver
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Handle the StockOutTask "creating" event.
     */
    public function creating(StockOutTask $task)
    {
        $facility = facility();

        if ($facility) {
            $task->owner_type = $facility->type;
            $task->owner_id = $facility->id;
        }

        if (empty($task->barcode)) {
            $task->barcode = strtoupper(uniqid());
        }
    }

    /**
     * Handle the StockOutTask "updating" event.
     */
    public function updating(StockOutTask $task)
    {
        $facility = facility();

        if ($facility) {
            $task->owner_type = $facility->type;
            $task->owner_id = $facility->id;
        }
    }
}
