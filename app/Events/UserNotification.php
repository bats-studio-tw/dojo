<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotification implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public string $message,
        public string $type = 'info',
        public array $data = []
    ) {
    }

    /**
     * 获取事件应该广播到的频道
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    /**
     * 事件广播名称
     */
    public function broadcastAs(): string
    {
        return 'user.notification';
    }

    /**
     * 要广播的数据
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
