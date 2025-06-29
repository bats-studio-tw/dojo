<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PredictionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $predictionData,
        public string $roundId,
        public string $type = 'prediction'
    ) {}

    /**
     * 获取事件应该广播到的频道
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('predictions'),
        ];
    }

    /**
     * 事件广播名称
     */
    public function broadcastAs(): string
    {
        return 'prediction.updated';
    }

    /**
     * 要广播的数据
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'round_id' => $this->roundId,
            'data' => $this->predictionData,
            'timestamp' => now()->toISOString(),
        ];
    }
}
