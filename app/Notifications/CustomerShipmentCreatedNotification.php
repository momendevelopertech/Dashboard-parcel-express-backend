<?php

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CustomerShipmentCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Shipment $shipment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Shipment $shipment)
    {
        $this->shipment = $shipment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type'        => 'customer_shipment_created',
            'title'       => 'New Customer Shipment Created',
            'body'        => "Shipment #{$this->shipment->tracking_no} created by customer and waiting for admin review.",
            'shipment_id' => $this->shipment->id,
            'tracking_no' => $this->shipment->tracking_no,
            'merchant_id' => $this->shipment->merchant_id,
            'created_by'  => $this->shipment->created_by,
        ];
    }
}
