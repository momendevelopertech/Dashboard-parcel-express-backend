<?php

namespace App\Observers;

use App\Models\Driver;
use App\Models\Hub;
use App\Models\Branch;
use App\Models\DriverSetting;
use App\Models\Station;
use Illuminate\Support\Facades\Auth;

class DriverObserver
{
    protected $adminCounterService;

    public function __construct(\App\Services\AdminCounterService $adminCounterService)
    {
        $this->adminCounterService = $adminCounterService;
    }

    /**
     * Handle the Driver "creating" event.
     *
     * @param  \App\Models\Driver  $driver
     * @return void
     */
    public function created(Driver $driver)
    {
        DriverSetting::firstOrCreate([
            'driver_id' => $driver->id
        ]);
        
        if ($driver->is_guest) {
             $this->adminCounterService->broadcastToAllAdmins();
        }
    }

    /**
     * Handle the Driver "updating" event.
     *
     * @param  \App\Models\Driver  $driver
     * @return void
     */
    public function updated(Driver $driver) {
         if ($driver->isDirty('is_guest') || $driver->isDirty('status')) {
             $this->adminCounterService->broadcastToAllAdmins();
         }
    }
    
    public function deleted(Driver $driver) {
         if ($driver->is_guest) {
             $this->adminCounterService->broadcastToAllAdmins();
         }
    }
}
