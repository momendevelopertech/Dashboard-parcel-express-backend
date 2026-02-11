<?php

namespace App\Notifications;

use App\Models\DeliveryReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class DeliveryReminderNotification extends Notification
{
    use Queueable;

    protected $reminder;

    public function __construct(DeliveryReminder $reminder)
    {
        $this->reminder = $reminder;
    }

    public function via($notifiable)
    {
        return $this->reminder->methods;
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Reminder: Shipment {$this->reminder->shipment_id}")
            ->line("Your delivery is scheduled at {$this->reminder->reminder_time->format('Y-m-d H:i')}.");
    }
}
