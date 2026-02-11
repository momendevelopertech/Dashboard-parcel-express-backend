<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CustomAlertTriggered extends Notification
{
    use Queueable;

    protected $alert;

    public function __construct($alert)
    {
        $this->alert = $alert;
    }

    public function via($notifiable)
    {
        return $this->alert->notification_methods;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Alert: {$this->alert->alert_name}")
            ->line("Condition \"{$this->alert->condition}\" has been met.");
    }
}
