<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\DexPriceClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchInitialPriceJob implements ShouldQueue
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
     */
    public function handle(DexPriceClient $dexPriceClient): void
    {
        $startTime = microtime(true);

        try {
            Log::info('[FetchInitialPriceJob] 开始获取初始价格', [
                'round_id' => $this->roundId,
                'symbols' => $this->symbols
            ]);

            // 获取初始价格 P0
            $pricesP0 = $dexPriceClient->batchPrice($this->symbols);

            Log::info('[FetchInitialPriceJob] 初始价格获取完成', [
                'round_id' => $this->roundId,
                'prices_p0' => $pricesP0
            ]);

            // 将初始价格存储到缓存中
            $cacheKey = "price_p0:{$this->roundId}";
            Cache::put($cacheKey, $pricesP0, 60); // 缓存1分钟

            // 调度延迟的第二个 Job 来计算动能
            CalculateMomentumJob::dispatch($this->roundId, $this->symbols, $this->chainId)
                ->delay(now()->addSeconds(10));

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::info('[FetchInitialPriceJob] 任务执行完成', [
                'round_id' => $this->roundId,
                'execution_time_ms' => $executionTime,
                'scheduled_momentum_job' => true
            ]);

        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            Log::error('[FetchInitialPriceJob] 任务执行时发生严重错误', [
                'round_id' => $this->roundId,
                'symbols' => $this->symbols,
                'execution_time_ms' => $executionTime,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[FetchInitialPriceJob] 任务执行失败', [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'chain_id' => $this->chainId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
