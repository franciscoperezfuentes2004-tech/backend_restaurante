<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CashCutConfirmed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $cashCutId;
    public $userId;
    public $userName;
    public $confirmedBy;
    public $receivedCash;

    /**
     * Create a new event instance.
     */
    public function __construct(
        $cashCutId,
        $userId,
        string $userName = 'Usuario',
        string $confirmedBy = 'Administración',
        $receivedCash = null
    ) {
        $this->cashCutId = $cashCutId;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->confirmedBy = $confirmedBy;
        $this->receivedCash = $receivedCash;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return [
            new Channel('delivery.cuts'),
            new PrivateChannel('delivery.cuts'),
            new PrivateChannel('admin.notifications'),
        ];
    }

    /**
     * Broadcast event name.
     */
    public function broadcastAs()
    {
        return 'cash.cut.confirmed';
    }

    /**
     * Data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        return [
            'cash_cut_id'   => $this->cashCutId,
            'user_id'       => $this->userId,
            'user_name'     => $this->userName,
            'confirmed_by'  => $this->confirmedBy,
            'received_cash' => $this->receivedCash,
        ];
    }
}
