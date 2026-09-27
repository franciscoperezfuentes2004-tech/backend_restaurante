<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryCutNotified implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $cutData;

    /**
     * Create a new event instance.
     */
    public function __construct(array $cutData)
    {
        $this->cutData = $cutData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('kitchen.orders'),
            new PrivateChannel('admin.notifications'),
            new Channel('delivery.cuts'),
        ];
    }

    public function broadcastAs()
    {
        return 'delivery.cut.notified';
    }
}
