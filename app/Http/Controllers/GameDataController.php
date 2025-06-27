<?php

namespace App\Http\Controllers;

use App\Models\GameRound;
use App\Models\RoundResult;
use App\Models\RoundPredict;
use App\Services\GamePredictionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GameDataController extends Controller
{
    public function __construct(
        private GamePredictionService $predictionService
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

            // 获取市场数据并计算增强预测评分
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
                            // 使用智能匹配找到最适合的代币
                            $bestMatch = $this->findBestTokenMatch($data['pairs'], $symbol);
                            if ($bestMatch) {
                                $marketData = [
                                    'price' => $bestMatch['priceUsd'] ?? '0',
                                    'change_5m' => $bestMatch['priceChange']['m5'] ?? null,
                                    'change_1h' => $bestMatch['priceChange']['h1'] ?? null,
                                    'change_4h' => $bestMatch['priceChange']['h4'] ?? null,
                                    'change_24h' => $bestMatch['priceChange']['h24'] ?? null,
                                    'volume_24h' => $bestMatch['volume']['h24'] ?? '0',
                                    'market_cap' => $bestMatch['marketCap'] ?? null,
                                    'logo' => $bestMatch['baseToken']['logoURI'] ?? null,
                                    'name' => $bestMatch['baseToken']['name'] ?? $symbol,
                                ];
                            }
                        }
                    }

                    // 合并预测数据和市场数据，确保symbol不被覆盖
                    $mergedData = array_merge($stats, $marketData);
                    $mergedData['symbol'] = $symbol; // 强制保持原始symbol

                    // 计算市场动量评分和最终预测评分
                    $mergedData = $this->calculateEnhancedPredictionScore($mergedData);

                    $analysisData[] = $mergedData;

                    // 延迟避免API限制
                    usleep(200000); // 0.2秒

                } catch (\Exception $e) {
                    Log::warning("获取{$symbol}市场数据失败", ['error' => $e->getMessage()]);

                    // 如果API失败，至少返回预测数据
                    $fallbackData = array_merge($stats, [
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
                    $fallbackData['symbol'] = $symbol; // 确保symbol正确

                    // 对fallback数据也计算增强评分
                    $fallbackData = $this->calculateEnhancedPredictionScore($fallbackData);
                    $analysisData[] = $fallbackData;
                }
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
     * 从多个交易对中找到最匹配的代币 (与GamePredictionService保持一致)
     * 在匹配的候选中选择流动性最高的
     */
    private function findBestTokenMatch(array $pairs, string $targetSymbol): ?array
    {
        $targetSymbol = strtoupper($targetSymbol);

        // 优先级1: 精确匹配 baseToken symbol，选择流动性最高的
        $exactMatches = [];
        foreach ($pairs as $pair) {
            $baseSymbol = strtoupper($pair['baseToken']['symbol'] ?? '');
            if ($baseSymbol === $targetSymbol) {
                $exactMatches[] = $pair;
            }
        }

        if (!empty($exactMatches)) {
            $bestMatch = $this->selectHighestLiquidityPair($exactMatches);
            Log::info("Controller: 找到精确匹配的代币，选择流动性最高的", [
                'target' => $targetSymbol,
                'matched' => $bestMatch['baseToken']['symbol'] ?? 'unknown',
                'name' => $bestMatch['baseToken']['name'] ?? 'unknown',
                'liquidity_usd' => $bestMatch['liquidity']['usd'] ?? 0
            ]);
            return $bestMatch;
        }

        // 优先级2: 部分匹配 baseToken symbol (前缀匹配)，选择流动性最高的
        $partialMatches = [];
        foreach ($pairs as $pair) {
            $baseSymbol = strtoupper($pair['baseToken']['symbol'] ?? '');
            if (str_starts_with($baseSymbol, $targetSymbol) || str_starts_with($targetSymbol, $baseSymbol)) {
                $partialMatches[] = $pair;
            }
        }

        if (!empty($partialMatches)) {
            $bestMatch = $this->selectHighestLiquidityPair($partialMatches);
            Log::info("Controller: 找到部分匹配的代币，选择流动性最高的", [
                'target' => $targetSymbol,
                'matched' => $bestMatch['baseToken']['symbol'] ?? 'unknown',
                'name' => $bestMatch['baseToken']['name'] ?? 'unknown',
                'liquidity_usd' => $bestMatch['liquidity']['usd'] ?? 0
            ]);
            return $bestMatch;
        }

        // 优先级3: 检查代币名称中是否包含目标符号，选择流动性最高的
        $nameMatches = [];
        foreach ($pairs as $pair) {
            $tokenName = strtoupper($pair['baseToken']['name'] ?? '');
            if (str_contains($tokenName, $targetSymbol)) {
                $nameMatches[] = $pair;
            }
        }

        if (!empty($nameMatches)) {
            $bestMatch = $this->selectHighestLiquidityPair($nameMatches);
            Log::info("Controller: 通过名称匹配找到代币，选择流动性最高的", [
                'target' => $targetSymbol,
                'matched_name' => $bestMatch['baseToken']['name'] ?? 'unknown',
                'symbol' => $bestMatch['baseToken']['symbol'] ?? 'unknown',
                'liquidity_usd' => $bestMatch['liquidity']['usd'] ?? 0
            ]);
            return $bestMatch;
        }

        // 优先级4: 返回流动性最高的结果（替代原有的第一个）
        if (!empty($pairs)) {
            $bestMatch = $this->selectHighestLiquidityPair($pairs);
            Log::warning("Controller: 使用流动性最高的搜索结果作为备选", [
                'target' => $targetSymbol,
                'fallback_symbol' => $bestMatch['baseToken']['symbol'] ?? 'unknown',
                'fallback_name' => $bestMatch['baseToken']['name'] ?? 'unknown',
                'liquidity_usd' => $bestMatch['liquidity']['usd'] ?? 0
            ]);
            return $bestMatch;
        }

        return null;
    }

    /**
     * 从交易对数组中选择流动性最高的
     */
    private function selectHighestLiquidityPair(array $pairs): ?array
    {
        if (empty($pairs)) {
            return null;
        }

        $bestPair = null;
        $highestLiquidity = 0;

        foreach ($pairs as $pair) {
            $liquidityUsd = floatval($pair['liquidity']['usd'] ?? 0);

            if ($liquidityUsd > $highestLiquidity) {
                $highestLiquidity = $liquidityUsd;
                $bestPair = $pair;
            }
        }

        return $bestPair ?? $pairs[0]; // 如果都没有流动性数据，返回第一个
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
                    })->values()->toArray();

                    // 构建实际结果数据
                    $results = $round->roundResults->sortBy('rank')->map(function ($result) {
                        return [
                            'symbol' => $result->token_symbol,
                            'actual_rank' => $result->rank,
                            'value' => $result->value,
                        ];
                    })->values()->toArray();

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
}
