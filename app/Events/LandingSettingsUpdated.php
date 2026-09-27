<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LandingSettingsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $settings;

    /**
     * Create a new event instance.
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return [
            new Channel('landing'),
            new Channel('landing.settings'),
            new Channel('landing_settings'),
            new Channel('landing-settings'),
            new Channel('settings'),
            new Channel('public'),
            new PrivateChannel('admin.notifications'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'landing_settings_updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return array_merge($this->settings, [
            'settings' => $this->settings,
            'data'     => $this->settings,
        ]);
    }
}
