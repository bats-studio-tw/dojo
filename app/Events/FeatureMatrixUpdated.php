<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FeatureMatrixUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $roundId,
        public array $matrixPayload
    ) {}

    public function broadcastOn(): array
    {
        // 复用公开频道，避免额外握手
        return [new Channel('predictions')];
    }

    public function broadcastAs(): string
    {
        return 'feature.matrix.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'feature_matrix',
            'round_id' => $this->roundId,
            'data' => $this->matrixPayload,
            'timestamp' => now()->toISOString(),
        ];
    }
}


