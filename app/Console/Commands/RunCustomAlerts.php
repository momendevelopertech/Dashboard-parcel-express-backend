<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CustomAlert;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CustomAlertTriggered;

class RunCustomAlerts extends Command
{
    protected $signature = 'alerts:run';
    protected $description = 'Evaluate and trigger custom alerts';

    public function handle()
    {
        $alerts = CustomAlert::where('status', 'active')->get();
        
        foreach ($alerts as $alert) {
            if ($this->evaluate($alert->condition)) {
                $alert->update(['triggered_at' => now()]);
                
                foreach ($alert->recipients as $role) {
                    $users = $this->getUsersByRole($role);
                    Notification::send($users, new CustomAlertTriggered($alert));
                }
            }
        }
    }

    protected function evaluate(string $condition): bool
    {
        if ($condition === 'Delayed Shipments > 5') {
            return \App\Models\Shipment::where('status', 'delayed')->count() > 5;
        }
        return false;
    }

    protected function getUsersByRole(string $role)
    {
        // Implement role-based user retrieval
        // This is a placeholder - you'll need to implement the actual logic
        // based on your user management system
        return \App\Models\User::whereHas('roles', function ($query) use ($role) {
            $query->where('name', $role);
        })->get();
    }
}
