<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StockLowNotification extends Notification
{
    use Queueable;

    protected $alert;

    public function __construct($alert)
    {
        $this->alert = $alert;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $item = $this->alert->item;
        return (new MailMessage)
            ->subject('Low Stock Alert: ' . $item->item_name)
            ->line("Item {$item->item_name} ({$item->item_id}) stock has fallen to {$item->current_stock}, below the minimum {$this->alert->minimum_stock_level}.")
            ->line('Please restock as soon as possible.');
    }

    public function toDatabase($notifiable)
    {
        return [
            'alert_id' => $this->alert->id,
            'inventory_item_id' => $this->alert->inventory_item_id,
            'current_stock' => $this->alert->item->current_stock,
            'minimum_stock_level' => $this->alert->minimum_stock_level,
            'notification_methods' => $this->alert->notification_methods,
        ];
    }
}
