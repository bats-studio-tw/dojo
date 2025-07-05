<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HybridPredictionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $source = 'hybrid_edge_v1';

    public function __construct(
        public array $predictionData,
        public string $roundId,
        public string $type = 'hybrid_prediction',
        string $source = 'hybrid_edge_v1'
    ) {
        $this->source = $source;
    }

    /**
     * 获取事件应该广播到的频道
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('hybrid-predictions'),
        ];
    }

    /**
     * 事件广播名称
     */
    public function broadcastAs(): string
    {
        return 'hybrid.prediction.updated';
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
            'source' => $this->source,
            'algorithm' => 'Hybrid-Edge v1.0',
            'timestamp' => now()->toISOString(),
        ];
    }
}
