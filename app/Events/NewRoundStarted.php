<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewRoundStarted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $roundId;

    public $symbols; // 本局的 5 个候选代币符号

    public $chainId; // 链 ID (例如 'ethereum')

    /**
     * 创建一个新的事件实例。
     *
     * @param  string  $roundId  游戏回合 ID
     * @param  array  $symbols  代币符号数组
     * @param  string  $chainId  链 ID
     */
    public function __construct(string $roundId, array $symbols, string $chainId = 'ethereum')
    {
        $this->roundId = $roundId;
        $this->symbols = $symbols;
        $this->chainId = $chainId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
