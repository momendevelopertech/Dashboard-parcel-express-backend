<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Rule;
use Illuminate\Support\Facades\Notification;
use App\Notifications\RuleTriggeredNotification;

class RunRulesEngine extends Command
{
    protected $signature = 'rules:run';
    protected $description = 'Evaluate active rules and trigger actions';

    public function handle()
    {
        $this->info('Running rules engine...');
        $rules = Rule::where('status', 'active')->get();
        foreach ($rules as $rule) {
            if ($this->evaluate($rule)) {
                $this->info("Rule '{$rule->name}' triggered.");
                $this->executeAction($rule);
                $rule->triggered_at = now();
                $rule->save();
            }
        }
        $this->info('Rules engine run complete.');
    }

    protected function evaluate(Rule $rule): bool
    {
        // This is a placeholder for the actual evaluation logic.
        // The logic from the spec requires other models like Shipment and User which might not exist yet.
        // You can fill this in with the actual logic when ready.
        switch ($rule->condition_type) {
            case 'shipment_status':
                // Example: return \App\Models\Shipment::where('status', $rule->condition_value)->exists();
                return false; // Placeholder
            case 'delay_time':
                // Example: [$op, $val] = sscanf($rule->condition_value, '%1s%d');
                // return \App\Models\Shipment::whereRaw(
                //     "TIMESTAMPDIFF(HOUR, scheduled_delivery_time, actual_delivery_time) {$op} ?", [$val]
                // )->exists();
                return false; // Placeholder
        }
        return false;
    }

    protected function executeAction(Rule $rule)
    {
        if ($rule->action_type === 'send_alert') {
            $payload = $rule->action_payload;
            $recipients = $payload['recipients'] ?? [];
            foreach ($recipients as $role) {
                // This is a placeholder for getting users by role.
                // The logic from the spec requires a User model with roles.
                // $users = $this->getUsersByRole($role);
                // Notification::send($users, new RuleTriggeredNotification($rule));
            }
        }
    }
}
