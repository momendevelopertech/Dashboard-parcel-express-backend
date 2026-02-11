<?php

namespace App\Events;

use App\Models\ShipmentDelivery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfdCountChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $shipmentDelivery;
    public $oldOfdCount;
    public $newOfdCount;

    /**
     * Create a new event instance.
     */
    public function __construct(ShipmentDelivery $shipmentDelivery, $oldOfdCount, $newOfdCount)
    {
        $this->shipmentDelivery = $shipmentDelivery;
        $this->oldOfdCount = $oldOfdCount;
        $this->newOfdCount = $newOfdCount;
    }
}
