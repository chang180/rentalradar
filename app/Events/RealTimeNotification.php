<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealTimeNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $message,
        public string $type = 'info',
        public ?array $data = null,
        public ?string $userId = null
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [new Channel('notifications')];

        if ($this->userId) {
            $channels[] = new Channel("user.{$this->userId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => uniqid('notif_'),
            'message' => $this->message,
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
            'read' => false,
        ];
    }
}
