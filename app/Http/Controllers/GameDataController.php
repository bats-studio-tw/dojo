<?php

namespace App\Http\Controllers;

use App\Models\GameRound;
use App\Models\RoundResult;
use App\Models\RoundPredict;
use Illuminate\Http\Request;
use App\Services\DexPriceClient;
use Illuminate\Http\JsonResponse;
use App\Models\HybridRoundPredict;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\GamePredictionService;

class GameDataController extends Controller
{
    public function __construct(
        private GamePredictionService $predictionService,
        private DexPriceClient $dexPriceClient
    ) {}
    /**
     * 获取历史游戏数据（最近50局）
     */
    public function getHistoryData(): JsonResponse
    {
        try {
            $rounds = GameRound::with(['roundResults' => function ($query) {
                $query->orderBy('rank');
            }])
            ->orderBy('created_at', 'desc')
            ->limit(50)
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

            // 使用 DexPriceClient 批量获取市场数据
            $batchMarketData = $this->dexPriceClient->batchMarketData($tokenSymbols);

            $marketData = [];
            foreach ($tokenSymbols as $symbol) {
                $symbolUpper = strtoupper($symbol);
                $data = $batchMarketData[$symbolUpper] ?? [];

                $marketData[] = [
                    'symbol' => $symbolUpper,
                    'name' => $data['name'] ?? $symbol,
                    'price' => $data['price'] ?? '0',
                    'change_5m' => $data['change_5m'] ?? null,
                    'change_1h' => $data['change_1h'] ?? null,
                    'change_4h' => $data['change_4h'] ?? null,
                    'change_24h' => $data['change_24h'] ?? null,
                    'volume_24h' => $data['volume_24h'] ?? '0',
                    'market_cap' => $data['market_cap'] ?? null,
                    'logo' => $data['logo'] ?? null,
                ];
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

            // 使用 DexPriceClient 批量获取市场数据
            $batchMarketData = $this->dexPriceClient->batchMarketData($tokenSymbols);

            $marketData = [];
            foreach ($tokenSymbols as $symbol) {
                $symbolUpper = strtoupper($symbol);
                $data = $batchMarketData[$symbolUpper] ?? [];

                $marketData[] = [
                    'symbol' => $symbol,
                    'name' => $data['name'] ?? $symbol,
                    'price' => $data['price'] ?? '0',
                    'change_5m' => $data['change_5m'] ?? null,
                    'change_1h' => $data['change_1h'] ?? null,
                    'change_4h' => $data['change_4h'] ?? null,
                    'change_24h' => $data['change_24h'] ?? null,
                    'volume_24h' => $data['volume_24h'] ?? '0',
                    'market_cap' => $data['market_cap'] ?? null,
                    'logo' => $data['logo'] ?? null,
                ];
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
            // 优先从缓存获取预计算的分析数据
            $cachedPrediction = $this->predictionService->getCachedPrediction();

            if ($cachedPrediction) {
                Log::info('从缓存获取预测分析数据', [
                    'round_id' => $cachedPrediction['round_id'],
                    'generated_at' => $cachedPrediction['generated_at']
                ]);

                // 获取当前局信息用于meta数据
                $roundInfo = Cache::get('game:current_round');

                return response()->json([
                    'success' => true,
                    'data' => $cachedPrediction['analysis_data'],
                    'meta' => [
                        'round_id' => $cachedPrediction['round_id'],
                        'status' => $roundInfo['status'] ?? 'unknown',
                        'current_tokens' => array_column($cachedPrediction['analysis_data'], 'symbol'),
                        'analysis_rounds' => $cachedPrediction['analysis_rounds_count'] ?? 0,
                        'prediction_algorithm' => $cachedPrediction['algorithm'] ?? 'cached',
                        'timestamp' => $roundInfo['timestamp'] ?? null,
                        'generated_at' => $cachedPrediction['generated_at'],
                        'source' => 'cached_prediction'
                    ]
                ]);
            }

            // 如果缓存中没有预测数据，回退到实时计算
            Log::info('缓存中没有预测数据，回退到实时计算');

            // 获取当前局的代币信息
            $currentTokens = $this->getCurrentRoundTokens();

            if (empty($currentTokens)) {
                return response()->json([
                    'success' => false,
                    'message' => '无法获取当前局代币信息',
                ]);
            }

            // 确保代币列表无重复
            $currentTokens = array_unique($currentTokens);

            Log::info('开始实时计算当前局代币排名', [
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

                    // 改进的预测评分算法 - 降低历史数据权重，预留30%给市场数据
                    $stats['prediction_score'] = (
                        ($stats['win_rate'] * 0.15) +                // 降低胜率权重从30%到15%
                        ($stats['top3_rate'] * 0.20) +               // 降低前三率权重从25%到20%
                        (((5 - $stats['avg_rank']) / 4) * 100 * 0.20) + // 降低平均排名权重从25%到20%
                        ($trendScore * 0.15) +                       // 降低趋势权重从20%到15%
                        (30)                                          // 预留30%权重给市场数据
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

            // 临时预测排名（将在获取市场数据后重新排序）
            $rank = 1;
            foreach ($tokenStats as &$stats) {
                $stats['predicted_rank'] = $rank++;
            }

            // 批量获取市场数据
            $tokenSymbols = array_keys($tokenStats);
            $batchMarketData = $this->dexPriceClient->batchMarketData($tokenSymbols);

            // 获取市场数据并计算增强预测评分
            $analysisData = [];
            foreach ($tokenStats as $symbol => $stats) {
                $symbolUpper = strtoupper($symbol);
                $marketData = $batchMarketData[$symbolUpper] ?? [];

                // 格式化市场数据
                $formattedMarketData = [
                    'price' => $marketData['price'] ?? '0',
                    'change_5m' => $marketData['change_5m'] ?? null,
                    'change_1h' => $marketData['change_1h'] ?? null,
                    'change_4h' => $marketData['change_4h'] ?? null,
                    'change_24h' => $marketData['change_24h'] ?? null,
                    'volume_24h' => $marketData['volume_24h'] ?? '0',
                    'market_cap' => $marketData['market_cap'] ?? null,
                    'logo' => $marketData['logo'] ?? null,
                    'name' => $marketData['name'] ?? $symbol,
                ];

                // 合并预测数据和市场数据，确保symbol不被覆盖
                $mergedData = array_merge($stats, $formattedMarketData);
                $mergedData['symbol'] = $symbol; // 强制保持原始symbol

                // 计算市场动量评分和最终预测评分
                $mergedData = $this->calculateEnhancedPredictionScore($mergedData);

                $analysisData[] = $mergedData;
            }

            // 重新排序基于最终预测评分
            usort($analysisData, function ($a, $b) {
                return $b['final_prediction_score'] <=> $a['final_prediction_score'];
            });

            // 重新分配预测排名
            foreach ($analysisData as $index => &$data) {
                $data['predicted_rank'] = $index + 1;
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
                    'prediction_algorithm' => 'enhanced_market_momentum_v2',
                    'timestamp' => $roundInfo['timestamp'] ?? null,
                    'source' => 'realtime_calculation'
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
     * 获取当前局的 Hybrid-Edge v1.0 動能預測數據
     */
    public function getHybridPredictions(): JsonResponse
    {
        try {
            // 获取当前轮次信息
            $roundInfo = Cache::get('game:current_round');
            $roundId = $roundInfo['round_id'] ?? null;

            if (!$roundId) {
                return response()->json([
                    'success' => false,
                    'message' => '当前没有活跃的游戏轮次',
                    'data' => []
                ]);
            }

            // 从缓存获取 Hybrid-Edge 预测数据
            $cachedPredictions = Cache::get("hybrid_prediction:{$roundId}");

            if ($cachedPredictions) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedPredictions,
                    'meta' => [
                        'round_id' => $roundId,
                        'status' => $roundInfo['status'] ?? 'unknown',
                        'prediction_method' => 'hybrid_edge_v1',
                        'timestamp' => $roundInfo['timestamp'] ?? null,
                        'source' => 'cached_hybrid_prediction'
                    ]
                ]);
            }

            // 如果缓存中没有，从数据库获取最新的 Hybrid-Edge 预测
            // 首先通过 round_id 找到对应的 GameRound 记录
            $gameRound = GameRound::where('round_id', $roundId)->first();

            if (!$gameRound) {
                return response()->json([
                    'success' => false,
                    'message' => '找不到对应的游戏轮次',
                    'data' => []
                ]);
            }

            $hybridPredictions = HybridRoundPredict::where('game_round_id', $gameRound->id)
                ->orderBy('predicted_rank')
                ->get()
                ->map(function ($prediction) {
                    return [
                        'symbol' => $prediction->token_symbol,
                        'predicted_rank' => $prediction->predicted_rank,
                        'final_score' => $prediction->final_score,
                        'elo_prob' => $prediction->elo_prob,
                        'mom_score' => $prediction->mom_score,
                        'confidence' => $prediction->confidence,
                    ];
                })
                ->toArray();

            if (empty($hybridPredictions)) {
                return response()->json([
                    'success' => false,
                    'message' => '暂无 Hybrid-Edge 预测数据',
                    'data' => []
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $hybridPredictions,
                'meta' => [
                    'round_id' => $roundId,
                    'status' => $roundInfo['status'] ?? 'unknown',
                    'prediction_method' => 'hybrid_edge_v1',
                    'timestamp' => $roundInfo['timestamp'] ?? null,
                    'source' => 'database_hybrid_prediction'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取 Hybrid-Edge 预测数据失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取 Hybrid-Edge 预测数据失败',
                'data' => []
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

    /**
     * 计算包含市场数据的增强预测评分
     */
    private function calculateEnhancedPredictionScore(array $data): array
    {
        // 基础历史评分（已经计算过，占70%权重）
        $historicalScore = $data['prediction_score'];

        // 计算市场动量评分（占30%权重）
        $marketMomentumScore = $this->calculateMarketMomentumScore($data);

        // 最终预测评分
        $data['market_momentum_score'] = round($marketMomentumScore, 1);
        $data['final_prediction_score'] = round(($historicalScore * 0.7) + ($marketMomentumScore * 0.3), 1);

        return $data;
    }

    /**
     * 计算市场动量评分
     */
    private function calculateMarketMomentumScore(array $data): float
    {
        // 获取价格变化数据
        $change5m = $this->normalizeChange($data['change_5m'] ?? 0);
        $change1h = $this->normalizeChange($data['change_1h'] ?? 0);
        $change4h = $this->normalizeChange($data['change_4h'] ?? 0);
        $change24h = $this->normalizeChange($data['change_24h'] ?? 0);

        // 计算交易量评分（相对交易量越高越好）
        $volumeScore = $this->calculateVolumeScore($data['volume_24h'] ?? '0');

        // 计算动量评分 - 近期变化权重更高
        $momentumScore = (
            ($change5m * 0.4) +   // 5分钟变化权重40%（最重要）
            ($change1h * 0.3) +   // 1小时变化权重30%
            ($change4h * 0.2) +   // 4小时变化权重20%
            ($change24h * 0.1)    // 24小时变化权重10%
        );

        // 综合市场评分：动量70% + 交易量30%
        $marketScore = ($momentumScore * 0.7) + ($volumeScore * 0.3);

        // 确保评分在0-100范围内
        return max(0, min(100, $marketScore));
    }

    /**
     * 标准化价格变化为0-100评分
     */
    private function normalizeChange(float $change): float
    {
        if ($change === 0) {
            return 50; // 无变化给中等评分
        }

        // 将-10%到+10%的变化映射到0-100分
        // 正向变化得分更高
        $normalizedChange = ($change + 10) / 20 * 100;

        // 确保在0-100范围内，并给正向变化额外加分
        $score = max(0, min(100, $normalizedChange));

        // 正向趋势加权：正向变化得分更高
        if ($change > 0) {
            $score = min(100, $score + 10); // 正向变化额外加10分
        }

        return $score;
    }

    /**
     * 计算交易量评分
     */
    private function calculateVolumeScore(string $volume): float
    {
        $volumeValue = floatval($volume);

        if ($volumeValue <= 0) {
            return 30; // 无交易量数据给低分
        }

        // 对数缩放处理交易量，避免极端值
        $logVolume = log10($volumeValue + 1);

        // 将对数交易量映射到30-100分（保证最低30分）
        // 假设log交易量在3-8之间（1K-100M USD）
        $score = 30 + (min($logVolume, 8) - 3) / 5 * 70;

        return max(30, min(100, $score));
    }

    /**
     * 获取预测历史数据（最近50局）
     */
    public function getPredictionHistory(): JsonResponse
    {
        try {
            // 获取最近50局的预测数据，并关联游戏轮次和实际结果
            $rounds = GameRound::with(['roundPredicts', 'roundResults'])
                ->whereHas('roundPredicts') // 只获取有预测数据的轮次
                ->orderBy('created_at', 'desc')
                ->limit(300)
                ->get()
                ->map(function ($round) {
                    // 构建预测数据
                    $predictions = $round->roundPredicts->sortBy('predicted_rank')->map(function ($predict) {
                        return [
                            'symbol' => $predict->token_symbol,
                            'predicted_rank' => $predict->predicted_rank,
                            'prediction_score' => $predict->prediction_score,
                            'predicted_at' => $predict->predicted_at?->format('Y-m-d H:i:s'),
                        ];
                    })->values()->toArray(); // 🔧 确保返回数组

                    // 构建实际结果数据
                    $results = $round->roundResults->sortBy('rank')->map(function ($result) {
                        return [
                            'symbol' => $result->token_symbol,
                            'actual_rank' => $result->rank,
                            'value' => $result->value,
                        ];
                    })->values()->toArray(); // 🔧 确保返回数组

                    // 计算预测准确度
                    $accuracy = $this->calculatePredictionAccuracy($predictions, $results);

                    return [
                        'id' => $round->id,
                        'round_id' => $round->round_id,
                        'settled_at' => $round->settled_at?->format('Y-m-d H:i:s'),
                        'predictions' => $predictions,
                        'results' => $results,
                        'accuracy' => $accuracy,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $rounds,
            ]);
        } catch (\Exception $e) {
            Log::error('获取预测历史数据失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取预测历史数据失败',
            ], 500);
        }
    }

    /**
     * 计算预测准确度
     */
    private function calculatePredictionAccuracy(array $predictions, array $results): array
    {
        if (empty($predictions) || empty($results)) {
            return [
                'total_predictions' => 0,
                'exact_matches' => 0,
                'close_matches' => 0,
                'exact_accuracy' => 0,
                'close_accuracy' => 0,
                'avg_rank_difference' => 0,
                'details' => []
            ];
        }

        $exactMatches = 0;
        $closeMatches = 0; // 排名差距在1以内
        $totalRankDifference = 0;
        $details = [];

        // 创建结果映射以便快速查找
        $resultMap = [];
        foreach ($results as $result) {
            $resultMap[$result['symbol']] = $result['actual_rank'];
        }

        foreach ($predictions as $prediction) {
            $symbol = $prediction['symbol'];
            $predictedRank = $prediction['predicted_rank'];

            if (isset($resultMap[$symbol])) {
                $actualRank = $resultMap[$symbol];
                $rankDifference = abs($predictedRank - $actualRank);

                $totalRankDifference += $rankDifference;

                if ($rankDifference === 0) {
                    $exactMatches++;
                    $closeMatches++;
                } elseif ($rankDifference === 1) {
                    $closeMatches++;
                }

                $details[] = [
                    'symbol' => $symbol,
                    'predicted_rank' => $predictedRank,
                    'actual_rank' => $actualRank,
                    'rank_difference' => $rankDifference,
                    'is_exact_match' => $rankDifference === 0,
                    'is_close_match' => $rankDifference <= 1,
                ];
            }
        }

        $totalPredictions = count($predictions);
        $avgRankDifference = $totalPredictions > 0 ? $totalRankDifference / $totalPredictions : 0;

        return [
            'total_predictions' => $totalPredictions,
            'exact_matches' => $exactMatches,
            'close_matches' => $closeMatches,
            'exact_accuracy' => $totalPredictions > 0 ? round(($exactMatches / $totalPredictions) * 100, 1) : 0,
            'close_accuracy' => $totalPredictions > 0 ? round(($closeMatches / $totalPredictions) * 100, 1) : 0,
            'avg_rank_difference' => round($avgRankDifference, 2),
            'details' => $details
        ];
    }

    /**
     * 获取Hybrid预测分析数据
     */
    public function getHybridAnalysis(): JsonResponse
    {
        try {
            // 获取当前轮次信息
            $roundInfo = Cache::get('game:current_round');
            $roundId = $roundInfo['round_id'] ?? null;

            if (!$roundId) {
                return response()->json([
                    'success' => false,
                    'message' => '当前没有活跃的游戏轮次',
                    'data' => [],
                    'meta' => null
                ]);
            }

            // 从缓存获取 Hybrid-Edge 预测数据
            $hybridPredictions = Cache::get("hybrid_prediction:{$roundId}");

            if (!$hybridPredictions || !is_array($hybridPredictions)) {
                // 如果缓存中没有，尝试从数据库获取
                $gameRound = \App\Models\GameRound::where('round_id', $roundId)->first();

                if ($gameRound) {
                    $hybridPredictions = \App\Models\HybridRoundPredict::where('game_round_id', $gameRound->id)
                        ->orderBy('predicted_rank')
                        ->get()
                        ->map(function ($prediction) {
                            return [
                                'symbol' => $prediction->token_symbol,
                                'predicted_rank' => $prediction->predicted_rank,
                                'final_score' => $prediction->final_score,
                                'elo_prob' => $prediction->elo_prob,
                                'mom_score' => $prediction->mom_score,
                                'confidence' => $prediction->confidence,
                            ];
                        })
                        ->toArray();
                }
            }

            if (!$hybridPredictions || !is_array($hybridPredictions) || empty($hybridPredictions)) {
                return response()->json([
                    'success' => false,
                    'message' => '暂无Hybrid预测数据',
                    'data' => [],
                    'meta' => null
                ]);
            }

            // 构造meta信息
            $meta = [
                'round_id' => $roundId,
                'status' => $roundInfo['status'] ?? 'unknown',
                'updated_at' => now()->toISOString(),
                'prediction_algorithm' => 'Hybrid-Edge v1.0',
                'source' => 'hybrid_edge_v1',
                'algorithm_description' => '结合Elo历史评分与5秒动能变化的智能预测算法'
            ];

            Log::info('获取Hybrid预测分析数据成功', [
                'round_id' => $roundId,
                'predictions_count' => count($hybridPredictions)
            ]);

            return response()->json([
                'success' => true,
                'message' => '获取Hybrid预测分析数据成功',
                'data' => $hybridPredictions,
                'meta' => $meta
            ]);

        } catch (\Exception $e) {
            Log::error('获取Hybrid预测分析数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取Hybrid预测分析数据失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取动能预测统计数据
     */
    public function getMomentumPredictionStats(Request $request): JsonResponse
    {
        try {
            $recentRounds = $request->get('recent_rounds', 50);
            $recentRounds = min(max($recentRounds, 1), 300); // 限制在1-300之间

            // 采用与 getPredictionHistory 相同的策略：简单获取数据，让前端计算统计
            $rounds = GameRound::with(['hybridRoundPredicts', 'roundResults'])
                ->whereHas('hybridRoundPredicts') // 只获取有动能预测数据的轮次
                ->whereNotNull('settled_at') // 只获取已结算的轮次
                ->orderBy('settled_at', 'desc')
                ->limit(300) // 限制最大数量
                ->get()
                ->map(function ($round) {
                    // 构建动能预测数据
                    $predictions = $round->hybridRoundPredicts->sortBy('predicted_rank')->map(function ($predict) {
                        return [
                            'symbol' => $predict->token_symbol,
                            'predicted_rank' => $predict->predicted_rank,
                            'momentum_score' => $predict->mom_score,
                            'confidence' => $predict->confidence,
                            'final_score' => $predict->final_score,
                            'elo_prob' => $predict->elo_prob,
                        ];
                    })->values()->toArray(); // 🔧 确保返回数组

                    // 构建实际结果数据
                    $results = $round->roundResults->sortBy('rank')->map(function ($result) {
                        return [
                            'symbol' => $result->token_symbol,
                            'actual_rank' => $result->rank,
                            'value' => $result->value,
                        ];
                    })->values()->toArray(); // 🔧 确保返回数组

                    return [
                        'id' => $round->id,
                        'round_id' => $round->round_id,
                        'settled_at' => $round->settled_at?->format('Y-m-d H:i:s'),
                        'predictions' => $predictions,
                        'results' => $results,
                    ];
                });

            // 计算简单的统计数据（避免复杂计算）
            $totalRounds = $rounds->count();
            $maxRounds = $totalRounds;

            // 如果没有数据，返回空结果
            if ($totalRounds === 0) {
                return response()->json([
                    'success' => true,
                    'message' => '暂无动能预测数据',
                    'data' => [
                        'momentum_accuracy' => 0,
                        'total_rounds' => 0,
                        'average_momentum_score' => 50,
                        'average_confidence' => 50,
                        'all_stats' => [
                            'rank1' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0],
                            'rank2' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0],
                            'rank3' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0]
                        ],
                        'recent_stats' => [
                            'rank1' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0],
                            'rank2' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0],
                            'rank3' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0]
                        ]
                    ],
                    'max_rounds' => 0
                ]);
            }

            Log::info('获取动能预测统计数据成功', [
                'total_rounds' => $totalRounds,
                'recent_rounds' => $recentRounds
            ]);

            return response()->json([
                'success' => true,
                'message' => '获取动能预测统计数据成功',
                'data' => [
                    'momentum_accuracy' => 0, // 前端计算
                    'total_rounds' => $totalRounds,
                    'average_momentum_score' => 50, // 前端计算
                    'average_confidence' => 50, // 前端计算
                    'all_stats' => [
                        'rank1' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0],
                        'rank2' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0],
                        'rank3' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0]
                    ],
                    'recent_stats' => [
                        'rank1' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0],
                        'rank2' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0],
                        'rank3' => ['total' => 0, 'breakeven' => 0, 'loss' => 0, 'first_place' => 0, 'breakeven_rate' => 0, 'loss_rate' => 0, 'first_place_rate' => 0]
                    ]
                ],
                'max_rounds' => $maxRounds,
                // 添加原始数据供前端使用
                'raw_data' => $rounds->toArray()
            ]);

        } catch (\Exception $e) {
            Log::error('获取动能预测统计数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取动能预测统计数据失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取动能预测历史数据
     */
    public function getMomentumPredictionHistory(): JsonResponse
    {
        try {
            // 获取所有已结算的回合，预加载关联数据
            $rounds = \App\Models\GameRound::with(['hybridRoundPredicts', 'roundResults'])
                ->whereHas('hybridRoundPredicts') // 只获取有动能预测数据的轮次
                ->whereNotNull('settled_at')
                ->orderBy('settled_at', 'desc')
                ->limit(300) // 增加限制到300轮，与统计数据保持一致
                ->get();

            if ($rounds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '暂无动能预测历史数据',
                    'data' => []
                ]);
            }

            $historyData = [];

            foreach ($rounds as $round) {
                // 使用预加载的数据，避免额外查询
                $predictions = $round->hybridRoundPredicts
                    ->sortBy('predicted_rank')
                    ->map(function ($prediction) {
                        return [
                            'symbol' => $prediction->token_symbol,
                            'predicted_rank' => $prediction->predicted_rank,
                            'momentum_score' => $prediction->mom_score,
                            'confidence' => $prediction->confidence
                        ];
                    })
                    ->values() // 🔧 修复：确保返回数组而不是对象
                    ->toArray();

                // 使用预加载的数据，避免额外查询
                $results = $round->roundResults
                    ->sortBy('rank')
                    ->map(function ($result) {
                        return [
                            'symbol' => $result->token_symbol,
                            'actual_rank' => $result->rank
                        ];
                    })
                    ->values() // 🔧 修复：确保返回数组而不是对象
                    ->toArray();

                // 🔧 修复：确保 predictions 和 results 都是数组，且不为空
                if (is_array($predictions) && is_array($results) && !empty($predictions) && !empty($results)) {
                    $historyData[] = [
                        'round_id' => $round->round_id,
                        'settled_at' => $round->settled_at?->toISOString(),
                        'predictions' => $predictions,
                        'results' => $results
                    ];
                } else {
                    // 记录数据不完整的轮次
                    Log::warning('轮次数据不完整，跳过', [
                        'round_id' => $round->round_id,
                        'predictions_count' => is_array($predictions) ? count($predictions) : 'not_array',
                        'results_count' => is_array($results) ? count($results) : 'not_array'
                    ]);
                }
            }

            Log::info('获取动能预测历史数据成功', [
                'total_rounds' => count($historyData)
            ]);

            return response()->json([
                'success' => true,
                'message' => '获取动能预测历史数据成功',
                'data' => $historyData
            ]);

        } catch (\Exception $e) {
            Log::error('获取动能预测历史数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取动能预测历史数据失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
