<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PredictionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $predictionData,
        public string $roundId,
        public string $type = 'prediction',
        public ?string $source = 'original' // 添加预测源标识符，允许为null以兼容旧队列任务
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
        // 检查是否是新的完整数据结构（包含success, data, meta）
        if ($this->type === 'current_analysis' && isset($this->predictionData['success'])) {
            // 对于current_analysis类型，直接传递完整的数据结构
            return $this->predictionData;
        }

        // 对于其他类型，保持原有结构
        return [
            'type' => $this->type,
            'round_id' => $this->roundId,
            'data' => $this->predictionData,
            'source' => $this->source ?? 'unknown', // 添加源标识，提供fallback值
            'timestamp' => now()->toISOString(),
        ];
    }
}
