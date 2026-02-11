<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminCountersUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $adminId;
    public $unassigned_count;
    public $unregistered_count;
     public $transfer_task_count;
    public $guest_driver_count;
    public $transfer_shipments_count;
    public $pickup_requests_count;
    public $cod_collection;
    public $pickup_collection;


    /**
     * Create a new event instance.
     */
    public function __construct($adminId, $unassigned_count, $unregistered_count,$transfer_task_count ,$guest_driver_count = 0, $transfer_shipments_count = 0, $pickup_requests_count = 0, $cod_collection = 0, $pickup_collection = 0)
    {
        $this->adminId = $adminId;
        $this->unassigned_count = $unassigned_count;
        $this->unregistered_count = $unregistered_count;
        $this->guest_driver_count = $guest_driver_count;
        $this->transfer_shipments_count = $transfer_shipments_count;
        $this->pickup_requests_count = $pickup_requests_count;
        $this->transfer_task_count = $transfer_task_count;
        $this->cod_collection = $cod_collection;
        $this->pickup_collection = $pickup_collection;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.' . $this->adminId),
        ];
    }

    public function broadcastWith()
    {
        $data = [
            'unassigned_count' => $this->unassigned_count,
            'unregistered_count' => $this->unregistered_count,
            'guest_driver_count' => $this->guest_driver_count,
            'transfer_shipments_count' => $this->transfer_shipments_count,
            "pickup_requests_count"=> $this->pickup_requests_count,
            "transfer_task_count"=> $this->transfer_task_count,
            "cod_collection"=> $this->cod_collection,
            "pickup_collection"=> $this->pickup_collection,
        ];
        return [
            'counters' => $data,
        ];
    }
    public function broadcastAs()
    {
        return 'NotificationsCounters';
    }
}
