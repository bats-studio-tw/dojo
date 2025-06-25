<?php

namespace App\Services;

use App\Models\GameRound;
use App\Models\RoundResult;
use App\Models\RoundPredict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class GameDataProcessorService
{
    /**
     * 處理遊戲結算資料
     *
     * @param array $gameData 從 WebSocket 收到的結算 payload
     */
    public function processSettlementData(array $gameData): void
    {
        $roundId = $gameData['rdId'] ?? null;
        $status = $gameData['status'] ?? 'unknown';

        if (!$roundId) {
            Log::warning('收到的結算資料缺少 rdId', ['gameData' => $gameData]);
            return;
        }

        // 添加调试日志
        Log::channel('websocket')->info('开始处理结算数据', [
            'rdId' => $roundId,
            'status' => $status,
            'hasTokenData' => isset($gameData['token'])
        ]);

        try {
            // 使用資料庫交易確保資料一致性
            DB::transaction(function () use ($gameData, $roundId, $status) {

                // 步驟一：找到或建立 GameRound 紀錄，並使用游戏提供的正确结算时间
                $round = GameRound::firstOrCreate(['round_id' => $roundId]);

                // 从游戏数据中提取正确的结算时间
                $settleTimestamp = $gameData['time']['now']['settle'] ?? null;

                if ($settleTimestamp && !$round->settled_at) {
                    $settleTime = \Carbon\Carbon::createFromTimestampMs($settleTimestamp);
                    $round->update(['settled_at' => $settleTime]);

                    Log::channel('websocket')->info('轮次已标记为结算', [
                        'round_id' => $roundId,
                        'settle_timestamp' => $settleTimestamp,
                        'settled_at' => $settleTime->toISOString(),
                        'source' => 'game_time_data'
                    ]);
                } elseif (!$settleTimestamp) {
                    // 如果没有时间数据，使用当前时间作为备用
                    Log::channel('websocket')->warning('游戏数据中缺少结算时间，使用当前时间', [
                        'round_id' => $roundId
                    ]);
                    if (!$round->settled_at) {
                        $round->update(['settled_at' => now()]);
                    }
                }

                Log::channel('websocket')->info('GameRound已创建', [
                    'round_id' => $roundId,
                    'database_id' => $round->id,
                    'is_new' => $round->wasRecentlyCreated
                ]);

                // 如果這筆 round 已經有 results，就不要重複處理
                if ($round->roundResults()->exists()) {
                    Log::channel('websocket')->info('結算資料已存在，跳過處理', [
                        'rdId' => $roundId,
                        'existing_results_count' => $round->roundResults()->count(),
                        'status' => $status
                    ]);
                    return;
                }

                // 步驟二：保存预测数据（如果缓存中有的话）
                $this->savePendingPredictionData($round, $roundId);

                // 步驟三：解析排名並為該局的每個代幣建立 RoundResult 紀錄
                $this->createRoundResults($round, $gameData);
            });

            Log::channel('websocket')->info('✅ 結算資料成功儲存到資料庫', ['rdId' => $roundId]);

        } catch (Exception $e) {
            Log::channel('websocket')->error("❌ 處理結算資料時發生錯誤", [
                'rdId' => $roundId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // 加上詳細追蹤訊息
            ]);
        }
    }

    /**
     * 為指定的遊戲輪次建立詳細的排名結果
     *
     * @param GameRound $gameRound
     * @param array $gameData
     */
    private function createRoundResults(GameRound $gameRound, array $gameData): void
    {
        if (!isset($gameData['token']) || !is_array($gameData['token'])) {
            Log::warning('結算資料中缺少 token 資訊', ['rdId' => $gameData['rdId']]);
            return;
        }

        $tokenCount = count($gameData['token']);
        $validResults = 0;

        Log::channel('websocket')->info('开始处理代币结果', [
            'rdId' => $gameData['rdId'],
            'tokenCount' => $tokenCount,
            'tokens' => array_keys($gameData['token'])
        ]);

        foreach ($gameData['token'] as $symbol => $details) {
            Log::channel('websocket')->info('处理代币数据', [
                'symbol' => $symbol,
                'details' => $details,
                'has_s' => isset($details['s']),
                'has_p' => isset($details['p'])
            ]);

            // 確保 payload 中有 's' (rank) 和 'p' (value)
            if (isset($details['s']) && isset($details['p'])) {
                // 修改：只要有排名就保存，包括价格为0的情况
                // 因为价格为0可能是游戏的正常状态
                $result = RoundResult::create([
                    'game_round_id' => $gameRound->id,
                    'token_symbol'  => strtoupper($symbol),
                    'rank'          => $details['s'],
                    'value'         => $details['p'], // 允许为0
                ]);

                $validResults++;

                Log::channel('websocket')->info('创建代币结果', [
                    'result_id' => $result->id,
                    'symbol' => $symbol,
                    'rank' => $details['s'],
                    'value' => $details['p'],
                    'is_zero_price' => $details['p'] == 0
                ]);
            } else {
                Log::channel('websocket')->warning('代币数据不完整', [
                    'symbol' => $symbol,
                    'details' => $details
                ]);
            }
        }

        Log::channel('websocket')->info('代币结果处理完成', [
            'rdId' => $gameData['rdId'],
            'totalTokens' => $tokenCount,
            'validResults' => $validResults
        ]);

        // 数据完整性验证
        if ($validResults != $tokenCount) {
            Log::channel('websocket')->warning('⚠️ 数据完整性警告：处理的代币数量与预期不符', [
                'rdId' => $gameData['rdId'],
                'expected' => $tokenCount,
                'actual' => $validResults,
                'missing_count' => $tokenCount - $validResults
            ]);
        } else {
            Log::channel('websocket')->info('✅ 数据完整性验证通过', [
                'rdId' => $gameData['rdId'],
                'processed_tokens' => $validResults
            ]);
        }
    }

    /**
     * 保存待处理的预测数据到数据库
     */
    private function savePendingPredictionData(GameRound $gameRound, string $roundId): void
    {
        try {
            // 从缓存获取预测数据
            $cachedPrediction = Cache::get('game:current_prediction');

            if (!$cachedPrediction || !is_array($cachedPrediction)) {
                Log::channel('websocket')->info('无缓存预测数据需要保存', ['round_id' => $roundId]);
                return;
            }

            // 检查缓存的预测数据是否对应当前结算的轮次
            $cachedRoundId = $cachedPrediction['round_id'] ?? null;
            if ($cachedRoundId !== $roundId) {
                Log::channel('websocket')->warning('缓存预测数据轮次不匹配，跳过保存', [
                    'current_round' => $roundId,
                    'cached_round' => $cachedRoundId
                ]);
                return;
            }

            $analysisData = $cachedPrediction['analysis_data'] ?? [];
            if (empty($analysisData)) {
                Log::channel('websocket')->warning('缓存预测数据为空', ['round_id' => $roundId]);
                return;
            }

            // 删除该轮次的旧预测数据（如果存在）
            RoundPredict::where('game_round_id', $gameRound->id)->delete();

            // 批量插入新的预测数据
            $predictionRecords = [];
            foreach ($analysisData as $tokenData) {
                $predictionRecords[] = [
                    'game_round_id' => $gameRound->id,
                    'token_symbol' => $tokenData['symbol'],
                    'predicted_rank' => $tokenData['predicted_rank'],
                    'prediction_score' => $tokenData['prediction_score'],
                    'prediction_data' => json_encode($tokenData),
                    'predicted_at' => \Carbon\Carbon::parse($cachedPrediction['generated_at']),
                ];
            }

            RoundPredict::insert($predictionRecords);

            Log::channel('websocket')->info('✅ 预测数据已保存到数据库', [
                'round_id' => $roundId,
                'game_round_id' => $gameRound->id,
                'predictions_count' => count($predictionRecords),
                'predicted_at' => $cachedPrediction['generated_at']
            ]);

            // 保存成功后清除缓存（可选）
            // Cache::forget('game:current_prediction');

        } catch (\Exception $e) {
            Log::channel('websocket')->error('保存预测数据到数据库失败', [
                'round_id' => $roundId,
                'game_round_id' => $gameRound->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
