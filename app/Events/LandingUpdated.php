<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LandingUpdated implements ShouldBroadcast, ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?array $settings;

    /**
     * Create a new event instance.
     *
     * @param array|null $settings
     */
    public function __construct(?array $settings = null)
    {
        $this->settings = $settings;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('public-landing'),
            new Channel('landing'),
            new Channel('landing_settings'),
            new Channel('landing-settings'),
            new Channel('settings'),
            new Channel('public'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'landing_updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message'   => 'Landing Page actualizada',
            'settings'  => $this->settings,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
