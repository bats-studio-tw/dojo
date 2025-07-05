<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PredictRoundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $roundId;
    protected $symbols;
    protected $chainId;

    /**
     * 创建一个新的 Job 实例。
     * @param string $roundId 游戏回合 ID
     * @param array $symbols 本局游戏的代币符号数组
     * @param string $chainId 链 ID
     */
    public function __construct(string $roundId, array $symbols, string $chainId = 'ethereum')
    {
        $this->roundId = $roundId;
        $this->symbols = array_map('strtoupper', $symbols);
        $this->chainId = $chainId;
    }

    /**
     * 执行 Job。
     * 注意：此方法已被重构为非阻塞架构，现在只是调度 FetchInitialPriceJob
     */
    public function handle(): void
    {
        Log::info('[PredictRoundJob] 开始调度预测流程', [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'chain_id' => $this->chainId
        ]);

        // 调度第一个 Job 来获取初始价格
        FetchInitialPriceJob::dispatch($this->roundId, $this->symbols, $this->chainId);

        Log::info('[PredictRoundJob] 预测流程调度完成', [
            'round_id' => $this->roundId,
            'scheduled_fetch_initial_price_job' => true
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[PredictRoundJob] 任务执行失败', [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'chain_id' => $this->chainId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
