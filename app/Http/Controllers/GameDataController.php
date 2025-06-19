<?php

namespace App\Http\Controllers;

use App\Models\GameRound;
use App\Models\RoundResult;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GameDataController extends Controller
{
    /**
     * 获取历史游戏数据（最近100局）
     */
    public function getHistoryData(): JsonResponse
    {
        try {
            $rounds = GameRound::with(['roundResults' => function ($query) {
                $query->orderBy('rank');
            }])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($round) {
                return [
                    'id' => $round->id,
                    'round_id' => $round->round_id,
                    'settled_at' => $round->settled_at?->format('Y-m-d H:i:s'),
                    'tokens' => $round->roundResults->map(function ($result) {
                        return [
                            'symbol' => $result->token_symbol,
                            'rank' => $result->rank,
                            'value' => $result->value,
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $rounds,
            ]);
        } catch (\Exception $e) {
            Log::error('获取历史数据失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取历史数据失败',
            ], 500);
        }
    }

    /**
     * 获取当前代币的市场数据（从DexScreener）
     */
    public function getTokenMarketData(): JsonResponse
    {
        try {
            // 获取最新一局的代币列表
            $latestRound = GameRound::with('roundResults')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestRound) {
                return response()->json([
                    'success' => false,
                    'message' => '暂无游戏数据',
                ]);
            }

            $tokenSymbols = $latestRound->roundResults->pluck('token_symbol')->unique();
            $marketData = [];

            foreach ($tokenSymbols as $symbol) {
                try {
                    // 调用DexScreener API获取代币市场数据
                    $response = Http::timeout(10)->get("https://api.dexscreener.com/latest/dex/search", [
                        'q' => $symbol
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['pairs']) && count($data['pairs']) > 0) {
                            $pair = $data['pairs'][0]; // 取第一个交易对

                            $marketData[] = [
                                'symbol' => $symbol,
                                'name' => $pair['baseToken']['name'] ?? $symbol,
                                'price' => $pair['priceUsd'] ?? '0',
                                'change_5m' => $pair['priceChange']['m5'] ?? null,
                                'change_1h' => $pair['priceChange']['h1'] ?? null,
                                'change_4h' => $pair['priceChange']['h4'] ?? null,
                                'change_24h' => $pair['priceChange']['h24'] ?? null,
                                'volume_24h' => $pair['volume']['h24'] ?? '0',
                                'market_cap' => $pair['marketCap'] ?? null,
                                'logo' => $pair['baseToken']['logoURI'] ?? null,
                            ];
                        }
                    }

                    // 延迟避免API限制
                    usleep(200000); // 0.2秒

                } catch (\Exception $e) {
                    Log::warning("获取{$symbol}市场数据失败", ['error' => $e->getMessage()]);
                    // 如果API失败，至少返回基本信息
                    $marketData[] = [
                        'symbol' => $symbol,
                        'name' => $symbol,
                        'price' => '0',
                        'change_5m' => null,
                        'change_1h' => null,
                        'change_4h' => null,
                        'change_24h' => null,
                        'volume_24h' => '0',
                        'market_cap' => null,
                        'logo' => null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $marketData,
            ]);

        } catch (\Exception $e) {
            Log::error('获取代币市场数据失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取市场数据失败',
            ], 500);
        }
    }

    /**
     * 获取预测数据（基于历史表现）
     */
    public function getPredictionData(): JsonResponse
    {
        try {
            // 获取最近20局的数据进行分析
            $recentRounds = GameRound::with('roundResults')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            if ($recentRounds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '暂无历史数据进行预测',
                ]);
            }

            // 统计每个代币的表现
            $tokenStats = [];

            foreach ($recentRounds as $round) {
                foreach ($round->roundResults as $result) {
                    $symbol = $result->token_symbol;

                    if (!isset($tokenStats[$symbol])) {
                        $tokenStats[$symbol] = [
                            'symbol' => $symbol,
                            'total_games' => 0,
                            'wins' => 0,
                            'top3' => 0,
                            'avg_rank' => 0,
                            'rank_sum' => 0,
                            'recent_trend' => [], // 最近5局的排名
                        ];
                    }

                    $tokenStats[$symbol]['total_games']++;
                    $tokenStats[$symbol]['rank_sum'] += $result->rank;

                    if ($result->rank === 1) {
                        $tokenStats[$symbol]['wins']++;
                    }

                    if ($result->rank <= 3) {
                        $tokenStats[$symbol]['top3']++;
                    }

                    // 记录最近的排名（用于趋势分析）
                    if (count($tokenStats[$symbol]['recent_trend']) < 5) {
                        $tokenStats[$symbol]['recent_trend'][] = $result->rank;
                    }
                }
            }

            // 计算统计数据和预测评分
            foreach ($tokenStats as $symbol => &$stats) {
                $stats['avg_rank'] = $stats['rank_sum'] / $stats['total_games'];
                $stats['win_rate'] = ($stats['wins'] / $stats['total_games']) * 100;
                $stats['top3_rate'] = ($stats['top3'] / $stats['total_games']) * 100;

                // 简单的预测评分算法（可以后续优化）
                $stats['prediction_score'] = (
                    ($stats['win_rate'] * 0.4) +
                    ($stats['top3_rate'] * 0.3) +
                    ((5 - $stats['avg_rank']) / 4 * 100 * 0.3)
                );
            }

            // 按预测评分排序
            uasort($tokenStats, function ($a, $b) {
                return $b['prediction_score'] <=> $a['prediction_score'];
            });

            return response()->json([
                'success' => true,
                'data' => array_values($tokenStats),
            ]);

        } catch (\Exception $e) {
            Log::error('获取预测数据失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取预测数据失败',
            ], 500);
        }
    }
}
