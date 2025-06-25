<?php

namespace App\Http\Controllers;

use App\Models\GameRound;
use App\Models\RoundResult;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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
     * 获取当前游戏局的代币市场数据（从Redis + DexScreener）
     */
    public function getTokenMarketData(): JsonResponse
    {
        try {
                        // 从缓存获取当前局的代币信息
            $roundInfo = Cache::get('game:current_round');

            if (!$roundInfo) {
                // 如果缓存中没有当前局数据，尝试从数据库获取最新一局作为备用
                Log::info('缓存中无当前局数据，使用最新已结算局数据作为备用');
                return $this->getLatestSettledRoundTokens();
            }

            if (!is_array($roundInfo) || !isset($roundInfo['tokens']) || !is_array($roundInfo['tokens'])) {
                Log::warning('缓存中的当前局数据格式异常', ['data' => $roundInfo]);
                return $this->getLatestSettledRoundTokens();
            }

            $tokenSymbols = $roundInfo['tokens'];
            $roundId = $roundInfo['round_id'] ?? 'unknown';
            $status = $roundInfo['status'] ?? 'unknown';

            Log::info('从缓存获取到当前局代币信息', [
                'round_id' => $roundId,
                'status' => $status,
                'tokens' => $tokenSymbols,
                'token_count' => count($tokenSymbols)
            ]);

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
                                'symbol' => strtoupper($symbol),
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
                        'symbol' => strtoupper($symbol),
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
                'meta' => [
                    'round_id' => $roundId,
                    'status' => $status,
                    'source' => 'current_round_cache',
                    'timestamp' => $roundInfo['timestamp'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取当前局代币市场数据失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取市场数据失败',
            ], 500);
        }
    }

    /**
     * 备用方法：获取最新已结算局的代币数据
     */
    private function getLatestSettledRoundTokens(): JsonResponse
    {
        try {
            $latestRound = GameRound::with('roundResults')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestRound) {
                return response()->json([
                    'success' => false,
                    'message' => '暂无游戏数据',
                ]);
            }

            $tokenSymbols = $latestRound->roundResults->pluck('token_symbol')->unique()->toArray();
            $marketData = [];

            foreach ($tokenSymbols as $symbol) {
                try {
                    $response = Http::timeout(10)->get("https://api.dexscreener.com/latest/dex/search", [
                        'q' => $symbol
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['pairs']) && count($data['pairs']) > 0) {
                            $pair = $data['pairs'][0];

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

                    usleep(200000);

                } catch (\Exception $e) {
                    Log::warning("获取{$symbol}市场数据失败", ['error' => $e->getMessage()]);
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
                'meta' => [
                    'round_id' => $latestRound->round_id,
                    'status' => 'settled',
                    'source' => 'latest_settled_round',
                    'settled_at' => $latestRound->settled_at?->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取备用代币市场数据失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取市场数据失败',
            ], 500);
        }
    }

    /**
     * 获取当前局完整分析数据（预测+市场信息）
     */
    public function getCurrentRoundAnalysis(): JsonResponse
    {
        try {
            // 获取当前局的代币信息
            $currentTokens = $this->getCurrentRoundTokens();

            if (empty($currentTokens)) {
                return response()->json([
                    'success' => false,
                    'message' => '无法获取当前局代币信息',
                ]);
            }

            Log::info('开始预测当前局代币排名', [
                'tokens' => $currentTokens,
                'token_count' => count($currentTokens)
            ]);

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

            // 只统计当前局参与代币的表现
            $tokenStats = [];

            // 初始化当前局所有代币的统计数据
            foreach ($currentTokens as $symbol) {
                $tokenStats[$symbol] = [
                    'symbol' => strtoupper($symbol),
                    'total_games' => 0,
                    'wins' => 0,
                    'top3' => 0,
                    'avg_rank' => 0,
                    'rank_sum' => 0,
                    'recent_trend' => [], // 最近5局的排名
                ];
            }

            // 分析历史数据
            foreach ($recentRounds as $round) {
                foreach ($round->roundResults as $result) {
                    $symbol = strtoupper($result->token_symbol);

                    // 只统计当前局参与的代币
                    if (!isset($tokenStats[$symbol])) {
                        continue;
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
                if ($stats['total_games'] > 0) {
                    $stats['avg_rank'] = $stats['rank_sum'] / $stats['total_games'];
                    $stats['win_rate'] = ($stats['wins'] / $stats['total_games']) * 100;
                    $stats['top3_rate'] = ($stats['top3'] / $stats['total_games']) * 100;

                    // 计算最近趋势得分（最近表现更好的得分更高）
                    $trendScore = 0;
                    if (!empty($stats['recent_trend'])) {
                        $recentAvg = array_sum($stats['recent_trend']) / count($stats['recent_trend']);
                        $trendScore = ((5 - $recentAvg) / 4) * 100;
                    }

                    // 综合预测评分算法
                    $stats['prediction_score'] = (
                        ($stats['win_rate'] * 0.3) +
                        ($stats['top3_rate'] * 0.25) +
                        (((5 - $stats['avg_rank']) / 4) * 100 * 0.25) +
                        ($trendScore * 0.2)
                    );
                } else {
                    // 如果没有历史数据，给予中等评分
                    $stats['avg_rank'] = 3;
                    $stats['win_rate'] = 0;
                    $stats['top3_rate'] = 0;
                    $stats['prediction_score'] = 50; // 中等评分
                }

                // 格式化数据
                $stats['avg_rank'] = round($stats['avg_rank'], 2);
                $stats['win_rate'] = round($stats['win_rate'], 1);
                $stats['top3_rate'] = round($stats['top3_rate'], 1);
                $stats['prediction_score'] = round($stats['prediction_score'], 1);
            }

            // 按预测评分排序（评分越高，预测排名越靠前）
            uasort($tokenStats, function ($a, $b) {
                return $b['prediction_score'] <=> $a['prediction_score'];
            });

            // 添加预测排名
            $rank = 1;
            foreach ($tokenStats as &$stats) {
                $stats['predicted_rank'] = $rank++;
            }

            // 获取市场数据
            $analysisData = [];
            foreach ($tokenStats as $symbol => $stats) {
                try {
                    // 调用DexScreener API获取市场数据
                    $response = Http::timeout(10)->get("https://api.dexscreener.com/latest/dex/search", [
                        'q' => $symbol
                    ]);

                    $marketData = [
                        'price' => '0',
                        'change_5m' => null,
                        'change_1h' => null,
                        'change_4h' => null,
                        'change_24h' => null,
                        'volume_24h' => '0',
                        'market_cap' => null,
                        'logo' => null,
                        'name' => $symbol,
                    ];

                    if ($response->successful()) {
                        $data = $response->json();
                        if (isset($data['pairs']) && count($data['pairs']) > 0) {
                            $pair = $data['pairs'][0];
                            $marketData = [
                                'price' => $pair['priceUsd'] ?? '0',
                                'change_5m' => $pair['priceChange']['m5'] ?? null,
                                'change_1h' => $pair['priceChange']['h1'] ?? null,
                                'change_4h' => $pair['priceChange']['h4'] ?? null,
                                'change_24h' => $pair['priceChange']['h24'] ?? null,
                                'volume_24h' => $pair['volume']['h24'] ?? '0',
                                'market_cap' => $pair['marketCap'] ?? null,
                                'logo' => $pair['baseToken']['logoURI'] ?? null,
                                'name' => $pair['baseToken']['name'] ?? $symbol,
                            ];
                        }
                    }

                    // 合并预测数据和市场数据
                    $analysisData[] = array_merge($stats, $marketData);

                    // 延迟避免API限制
                    usleep(200000); // 0.2秒

                } catch (\Exception $e) {
                    Log::warning("获取{$symbol}市场数据失败", ['error' => $e->getMessage()]);

                    // 如果API失败，至少返回预测数据
                    $analysisData[] = array_merge($stats, [
                        'price' => '0',
                        'change_5m' => null,
                        'change_1h' => null,
                        'change_4h' => null,
                        'change_24h' => null,
                        'volume_24h' => '0',
                        'market_cap' => null,
                        'logo' => null,
                        'name' => $symbol,
                    ]);
                }
            }

            // 获取当前局信息
            $roundInfo = Cache::get('game:current_round');
            $roundId = $roundInfo['round_id'] ?? 'unknown';
            $status = $roundInfo['status'] ?? 'unknown';

            return response()->json([
                'success' => true,
                'data' => $analysisData,
                'meta' => [
                    'round_id' => $roundId,
                    'status' => $status,
                    'current_tokens' => $currentTokens,
                    'analysis_rounds' => $recentRounds->count(),
                    'prediction_algorithm' => 'historical_performance_weighted',
                    'timestamp' => $roundInfo['timestamp'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取预测数据失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取预测数据失败',
            ], 500);
        }
    }

    /**
     * 获取当前局的代币列表
     */
    private function getCurrentRoundTokens(): array
    {
        try {
            // 首先从缓存获取当前局信息
            $roundInfo = Cache::get('game:current_round');

            if ($roundInfo && isset($roundInfo['tokens']) && is_array($roundInfo['tokens'])) {
                Log::info('从缓存获取当前局代币', [
                    'round_id' => $roundInfo['round_id'] ?? 'unknown',
                    'tokens' => $roundInfo['tokens']
                ]);
                return array_map('strtoupper', $roundInfo['tokens']);
            }

            // 如果缓存中没有，从数据库获取最新局的代币
            $latestRound = GameRound::with('roundResults')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestRound && $latestRound->roundResults->isNotEmpty()) {
                $tokens = $latestRound->roundResults->pluck('token_symbol')->unique()->map(function($token) {
                    return strtoupper($token);
                })->toArray();

                Log::info('从数据库获取最新局代币', [
                    'round_id' => $latestRound->round_id,
                    'tokens' => $tokens
                ]);

                return $tokens;
            }

            return [];
        } catch (\Exception $e) {
            Log::error('获取当前局代币失败', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
