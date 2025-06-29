<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $gameData,
        public string $type = 'game_data'
    ) {}

    /**
     * 获取事件应该广播到的频道
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('game-updates'),
        ];
    }

    /**
     * 事件广播名称
     */
    public function broadcastAs(): string
    {
        return 'game.data.updated';
    }

    /**
     * 要广播的数据
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->gameData,
            'timestamp' => now()->toISOString(),
        ];
    }
}
