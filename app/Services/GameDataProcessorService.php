<?php

namespace App\Services;

use App\Models\GameRound;
use App\Models\RoundResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

                // 步驟一：建立或找到 GameRound 紀錄
                // 我們只在收到結算資料時才建立，所以 settled_at 就是當前時間
                $round = GameRound::firstOrCreate(
                    ['round_id' => $roundId],
                    ['settled_at' => now()]
                );

                Log::channel('websocket')->info('GameRound已创建', [
                    'round_id' => $roundId,
                    'database_id' => $round->id,
                    'is_new' => $round->wasRecentlyCreated
                ]);

                // 如果這筆 round 已經有 results，就不要重複處理
                if ($round->roundResults()->exists()) {
                    Log::info('結算資料已存在，跳過處理', ['rdId' => $roundId]);
                    return;
                }

                // 步驟二：解析排名並為該局的每個代幣建立 RoundResult 紀錄
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
                // 只有当价格不为0时才存储（避免存储下注阶段的数据）
                if ($details['p'] != 0) {
                    $result = RoundResult::create([
                        'game_round_id' => $gameRound->id,
                        'token_symbol'  => strtoupper($symbol),
                        'rank'          => $details['s'],
                        'value'         => $details['p'],
                    ]);

                    $validResults++;

                    Log::channel('websocket')->info('创建代币结果', [
                        'result_id' => $result->id,
                        'symbol' => $symbol,
                        'rank' => $details['s'],
                        'value' => $details['p']
                    ]);
                } else {
                    Log::channel('websocket')->info('跳过价格为0的代币', [
                        'symbol' => $symbol,
                        'rank' => $details['s'],
                        'value' => $details['p']
                    ]);
                }
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
    }
}
