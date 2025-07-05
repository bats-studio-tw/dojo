<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\DexPriceClient;
use App\Services\EloRatingEngine;
use App\Services\ScoreMixer;
use App\Models\HybridRoundPredict;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Events\PredictionUpdated;

class PredictRoundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $roundId;
    protected $symbols;
    protected $chainId;

    /**
     * 創建一個新的 Job 實例。
     * @param string $roundId 遊戲回合 ID
     * @param array $symbols 本局遊戲的代幣符號陣列
     * @param string $chainId 鏈 ID
     */
    public function __construct(string $roundId, array $symbols, string $chainId = 'ethereum')
    {
        $this->roundId = $roundId;
        $this->symbols = array_map('strtoupper', $symbols);
        $this->chainId = $chainId;

        $this->onQueue('high');
    }

    /**
     * 執行 Job。
     */
    public function handle(
        DexPriceClient $dexPriceClient,
        EloRatingEngine $eloRatingEngine,
        ScoreMixer $scoreMixer
    ): void
    {
        try {
            Log::info('开始 Hybrid-Edge v1.0 预测', [
                'round_id' => $this->roundId,
                'symbols' => $this->symbols,
                'chain_id' => $this->chainId
            ]);

            $pricesP0 = $dexPriceClient->batchPrice($this->symbols);

            Log::info('获取初始价格 P0', [
                'round_id' => $this->roundId,
                'prices_p0' => $pricesP0
            ]);

            Log::info('等待 5 秒获取价格变化', ['round_id' => $this->roundId]);
            sleep(5);

            $pricesP1 = $dexPriceClient->batchPrice($this->symbols);

            Log::info('获取后续价格 P1', [
                'round_id' => $this->roundId,
                'prices_p1' => $pricesP1
            ]);

            $momScore = [];
            foreach ($this->symbols as $symbol) {
                if (isset($pricesP0[$symbol]) && isset($pricesP1[$symbol]) && $pricesP0[$symbol] > 0) {
                    $momentum = ($pricesP1[$symbol] / $pricesP0[$symbol] - 1) * 1000;
                    // 5秒間隔的動能計算：更敏感的參數調整
                    $momScore[$symbol] = min(100, max(0, 50 + ($momentum / 0.1)));
                } else {
                    $momScore[$symbol] = null;
                    Log::warning('无法计算动能分数', [
                        'symbol' => $symbol,
                        'price_p0' => $pricesP0[$symbol] ?? 'missing',
                        'price_p1' => $pricesP1[$symbol] ?? 'missing'
                    ]);
                }
            }

            Log::info('动能分数计算完成', [
                'round_id' => $this->roundId,
                'momentum_scores' => $momScore
            ]);

            $eloProb = $eloRatingEngine->probabilities($this->symbols);

            Log::info('Elo 机率计算完成', [
                'round_id' => $this->roundId,
                'elo_probabilities' => $eloProb
            ]);

            $predictions = $scoreMixer->mix($eloProb, $momScore);

            if (empty($predictions)) {
                Log::error('预测结果为空', ['round_id' => $this->roundId]);
                return;
            }

            Log::info('预测混合完成', [
                'round_id' => $this->roundId,
                'predictions_count' => count($predictions)
            ]);

            foreach ($predictions as $predictionData) {
                HybridRoundPredict::create(array_merge($predictionData, [
                    'game_round_id' => $this->roundId,
                    'token_symbol' => $predictionData['symbol'],
                ]));
            }

            Log::info('预测结果已保存到数据库', [
                'round_id' => $this->roundId,
                'saved_predictions' => count($predictions)
            ]);

            Cache::put("hybrid_prediction:{$this->roundId}", $predictions, 30);

            event(new PredictionUpdated($predictions, $this->roundId, 'prediction', 'hybrid'));

            Log::info('Hybrid-Edge v1.0 预测完成', [
                'round_id' => $this->roundId,
                'top_prediction' => $predictions[0] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Hybrid-Edge v1.0 预测失败', [
                'round_id' => $this->roundId,
                'symbols' => $this->symbols,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PredictRoundJob 执行失败', [
            'round_id' => $this->roundId,
            'symbols' => $this->symbols,
            'exception' => $exception->getMessage()
        ]);
    }
}
