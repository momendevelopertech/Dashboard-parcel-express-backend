<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Rule;

class RuleTriggeredNotification extends Notification
{
    use Queueable;

    protected $rule;

    public function __construct(Rule $rule)
    {
        $this->rule = $rule;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Rule Triggered: {$this->rule->name}")
            ->line("The alert '{$this->rule->name}' has been triggered.")
            ->line("Condition '{$this->rule->condition_type} {$this->rule->condition_value}' was met.")
            ->action('View Dashboard', url('/'));
    }

    public function toArray($notifiable)
    {
        return [
            'rule_id' => $this->rule->id,
            'rule_name' => $this->rule->name,
            'message' => "Condition '{$this->rule->condition_type} {$this->rule->condition_value}' was met."
        ];
    }
}
