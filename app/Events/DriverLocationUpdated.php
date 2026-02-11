<?php

namespace App\Events;

use App\Models\DriverLocation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $location;

    public function __construct(DriverLocation $location)
    {
        $this->location = $location->load('driver');
    }

    public function broadcastOn()
    {
        return new PrivateChannel('driver-locations');
    }

    public function broadcastWith()
    {
        return [
            'driver_id'   => $this->location->driver_id,
            'driver_name' => $this->location->driver->name,
            'latitude'    => $this->location->latitude,
            'longitude'   => $this->location->longitude,
            'status'      => $this->location->status,
            'last_updated'=> $this->location->last_updated->toDateTimeString(),
        ];
    }
}
