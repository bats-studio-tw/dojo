<?php

namespace App\Events;

use App\Models\PredictionResult;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewPredictionMade implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PredictionResult $predictionResult,
        public string $roundId,
        public string $type = 'new_prediction',
        public ?string $source = 'prediction_service'
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
        return 'NewPredictionMade';
    }

    /**
     * 要广播的数据
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'round_id' => $this->roundId,
            'prediction' => [
                'id' => $this->predictionResult->id,
                'game_round_id' => $this->predictionResult->game_round_id,
                'token' => $this->predictionResult->token,
                'predict_rank' => $this->predictionResult->predict_rank,
                'predict_score' => $this->predictionResult->predict_score,
                'elo_score' => $this->predictionResult->elo_score,
                'momentum_score' => $this->predictionResult->momentum_score,
                'volume_score' => $this->predictionResult->volume_score,
                'norm_elo' => $this->predictionResult->norm_elo,
                'norm_momentum' => $this->predictionResult->norm_momentum,
                'norm_volume' => $this->predictionResult->norm_volume,
                'used_weights' => $this->predictionResult->used_weights,
                'used_normalization' => $this->predictionResult->used_normalization,
                'strategy_tag' => $this->predictionResult->strategy_tag,
                'config_snapshot' => $this->predictionResult->config_snapshot,
                'created_at' => $this->predictionResult->created_at->toISOString(),
            ],
            'source' => $this->source,
            'timestamp' => now()->toISOString(),
        ];
    }
}
