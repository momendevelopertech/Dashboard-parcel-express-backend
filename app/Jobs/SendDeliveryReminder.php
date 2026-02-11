<?php

namespace App\Jobs;

use App\Models\DeliveryReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DeliveryReminderNotification;
use Illuminate\Foundation\Bus\Dispatchable;

class SendDeliveryReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $reminder;

    public function __construct(DeliveryReminder $reminder)
    {
        $this->reminder = $reminder;
    }

    public function handle()
    {
        if (! $this->reminder->enabled) return;

        $notifiable = $this->reminder->recipient === 'driver'
            ? $this->reminder->scheduledDelivery->driver
            : $this->reminder->scheduledDelivery->shipment->customer;

        Notification::send($notifiable, new DeliveryReminderNotification($this->reminder));

        $this->reminder->update(['status' => 'sent']);
    }
}
