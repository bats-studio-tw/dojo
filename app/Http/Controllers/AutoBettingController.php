<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\GameDataProcessorService;
use App\Services\GamePredictionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoBettingController extends Controller
{
    protected GameDataProcessorService $gameDataProcessor;
    protected GamePredictionService $gamePredictionService;

    public function __construct(
        GameDataProcessorService $gameDataProcessor,
        GamePredictionService $gamePredictionService
    ) {
        $this->gameDataProcessor = $gameDataProcessor;
        $this->gamePredictionService = $gamePredictionService;
    }

    /**
     * 显示自动下注控制页面
     */
    public function index(): Response
    {
        return Inertia::render('AutoBetting');
    }

    /**
     * 获取自动下注配置
     */
    public function getConfig(Request $request): JsonResponse
    {
        try {
            // 从缓存或数据库获取用户的自动下注配置
            $userId = $request->user()?->id ?? 'guest';
            $config = Cache::get("auto_betting_config_{$userId}", [
                'enabled' => false,
                'jwt_token' => '',
                'bankroll' => 1000,
                'unit_size_percentage' => 1.5,
                'daily_stop_loss_percentage' => 15,
                'confidence_threshold' => 88,
                'score_gap_threshold' => 6.0,
                'min_total_games' => 25,
                'strategy' => 'portfolio_hedging', // 'single_bet' or 'portfolio_hedging'
                'portfolio_allocation' => [
                    'rank1' => 50,
                    'rank2' => 30,
                    'rank3' => 20
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取配置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新自动下注配置
     */
    public function updateConfig(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
                'jwt_token' => 'nullable|string|max:1000',
                'bankroll' => 'required|numeric|min:1',
                'unit_size_percentage' => 'required|numeric|min:0.1|max:10',
                'daily_stop_loss_percentage' => 'required|numeric|min:1|max:50',
                'confidence_threshold' => 'required|numeric|min:50|max:100',
                'score_gap_threshold' => 'required|numeric|min:0.1|max:20',
                'min_total_games' => 'required|integer|min:1',
                'strategy' => 'required|in:single_bet,portfolio_hedging',
                'portfolio_allocation' => 'array',
                'portfolio_allocation.rank1' => 'required_if:strategy,portfolio_hedging|numeric|min:0|max:100',
                'portfolio_allocation.rank2' => 'required_if:strategy,portfolio_hedging|numeric|min:0|max:100',
                'portfolio_allocation.rank3' => 'required_if:strategy,portfolio_hedging|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '配置验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 验证投资组合分配总和为100%
            if ($request->strategy === 'portfolio_hedging') {
                $total = ($request->portfolio_allocation['rank1'] ?? 0) +
                        ($request->portfolio_allocation['rank2'] ?? 0) +
                        ($request->portfolio_allocation['rank3'] ?? 0);

                if (abs($total - 100) > 0.1) {
                    return response()->json([
                        'success' => false,
                        'message' => '投资组合分配总和必须为100%'
                    ], 422);
                }
            }

            $userId = $request->user()?->id ?? 'guest';
            $config = $request->only([
                'enabled', 'jwt_token', 'bankroll', 'unit_size_percentage',
                'daily_stop_loss_percentage', 'confidence_threshold',
                'score_gap_threshold', 'min_total_games', 'strategy',
                'portfolio_allocation'
            ]);

            // 缓存配置（实际项目中应存储到数据库）
            Cache::put("auto_betting_config_{$userId}", $config, now()->addDays(30));

            return response()->json([
                'success' => true,
                'message' => '配置已保存',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '保存配置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取自动下注状态
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id ?? 'guest';
            $status = Cache::get("auto_betting_status_{$userId}", [
                'is_running' => false,
                'current_round_id' => null,
                'last_bet_at' => null,
                'total_bets' => 0,
                'total_profit_loss' => 0,
                'today_profit_loss' => 0,
                'consecutive_losses' => 0,
                'last_error' => null
            ]);

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取状态失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 启动/停止自动下注
     */
    public function toggleAutoBetting(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:start,stop'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = $request->user()?->id ?? 'guest';
            $action = $request->action;

            if ($action === 'start') {
                // 检查配置是否完整
                $config = Cache::get("auto_betting_config_{$userId}");
                if (!$config || empty($config['jwt_token'])) {
                    return response()->json([
                        'success' => false,
                        'message' => '请先配置JWT Token和其他必要参数'
                    ], 422);
                }

                // 启动自动下注
                Cache::put("auto_betting_status_{$userId}", [
                    'is_running' => true,
                    'current_round_id' => null,
                    'last_bet_at' => null,
                    'total_bets' => 0,
                    'total_profit_loss' => 0,
                    'today_profit_loss' => 0,
                    'consecutive_losses' => 0,
                    'last_error' => null,
                    'started_at' => now()->toISOString()
                ], now()->addDays(1));

                $message = '自动下注已启动';
            } else {
                // 停止自动下注
                $status = Cache::get("auto_betting_status_{$userId}", []);
                $status['is_running'] = false;
                $status['stopped_at'] = now()->toISOString();
                Cache::put("auto_betting_status_{$userId}", $status, now()->addDays(1));

                $message = '自动下注已停止';
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '操作失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 测试JWT Token连接
     */
    public function testConnection(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'jwt_token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'JWT Token不能为空',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jwtToken = $request->jwt_token;

            // 简单格式验证
            if (strlen($jwtToken) < 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'JWT Token格式无效'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'JWT Token格式验证通过，请在前端测试实际连接',
                'data' => [
                    'token_valid' => true,
                    'connection_time' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '连接测试失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取下注历史记录
     */
    public function getBettingHistory(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id ?? 'guest';

            // 模拟下注历史数据（实际项目中应从数据库获取）
            $history = Cache::get("auto_betting_history_{$userId}", []);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取下注历史失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 执行单次下注模拟（用于测试）
     */
    public function simulateBet(Request $request): JsonResponse
    {
        try {
            // 获取当前分析数据
            $currentAnalysis = $this->gamePredictionService->getCurrentRoundPredictions();

            if (empty($currentAnalysis)) {
                return response()->json([
                    'success' => false,
                    'message' => '当前无可用分析数据'
                ], 422);
            }

            $userId = $request->user()?->id ?? 'guest';
            $config = Cache::get("auto_betting_config_{$userId}");

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => '请先配置自动下注参数'
                ], 422);
            }

            // 检查触发条件
            $trigger = $this->checkBettingTriggers($currentAnalysis, $config);

            return response()->json([
                'success' => true,
                'data' => [
                    'trigger_met' => $trigger['met'],
                    'trigger_details' => $trigger['details'],
                    'current_analysis' => $currentAnalysis,
                    'recommended_bets' => $trigger['met'] ? $this->calculateBetAmounts($currentAnalysis, $config) : []
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '模拟下注失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 检查下注触发条件
     */
    private function checkBettingTriggers(array $analysisData, array $config): array
    {
        $details = [];
        $allConditionsMet = true;

        if (empty($analysisData)) {
            return [
                'met' => false,
                'details' => ['no_data' => '无分析数据']
            ];
        }

        $topToken = $analysisData[0] ?? null;
        $secondToken = $analysisData[1] ?? null;

        // 条件1: 高度信赖分数
        $confidence = $topToken['rank_confidence'] ?? 0;
        $confidenceThreshold = $config['confidence_threshold'];
        $confidenceMet = $confidence >= $confidenceThreshold;
        $details['confidence'] = [
            'value' => $confidence,
            'threshold' => $confidenceThreshold,
            'met' => $confidenceMet
        ];
        if (!$confidenceMet) $allConditionsMet = false;

        // 条件2: 显著分数差距
        $topScore = $topToken['risk_adjusted_score'] ?? $topToken['final_prediction_score'] ?? 0;
        $secondScore = $secondToken['risk_adjusted_score'] ?? $secondToken['final_prediction_score'] ?? 0;
        $scoreGap = $topScore - $secondScore;
        $scoreGapThreshold = $config['score_gap_threshold'];
        $scoreGapMet = $scoreGap >= $scoreGapThreshold;
        $details['score_gap'] = [
            'value' => $scoreGap,
            'threshold' => $scoreGapThreshold,
            'met' => $scoreGapMet
        ];
        if (!$scoreGapMet) $allConditionsMet = false;

        // 条件3: 充足的历史数据
        $totalGames = $topToken['total_games'] ?? 0;
        $minGamesThreshold = $config['min_total_games'];
        $totalGamesMet = $totalGames >= $minGamesThreshold;
        $details['total_games'] = [
            'value' => $totalGames,
            'threshold' => $minGamesThreshold,
            'met' => $totalGamesMet
        ];
        if (!$totalGamesMet) $allConditionsMet = false;

        return [
            'met' => $allConditionsMet,
            'details' => $details
        ];
    }

    /**
     * 计算下注金额
     */
    private function calculateBetAmounts(array $analysisData, array $config): array
    {
        $bankroll = $config['bankroll'];
        $unitSizePercentage = $config['unit_size_percentage'];
        $baseUnitSize = $bankroll * ($unitSizePercentage / 100);
        $strategy = $config['strategy'];

        $bets = [];

        if ($strategy === 'single_bet') {
            // 单点突破策略：只下注预测第一名
            $topToken = $analysisData[0] ?? null;
            if ($topToken) {
                $confidence = ($topToken['rank_confidence'] ?? 0) / 100;
                $betAmount = $baseUnitSize * $confidence;

                $bets[] = [
                    'symbol' => $topToken['symbol'],
                    'predicted_rank' => 1,
                    'bet_amount' => round($betAmount, 2),
                    'confidence' => $topToken['rank_confidence'] ?? 0
                ];
            }
        } else {
            // 保本对冲组合策略：分散下注前几名
            $allocation = $config['portfolio_allocation'];

            foreach ([1, 2, 3] as $rank) {
                $token = $analysisData[$rank - 1] ?? null;
                if ($token && isset($allocation["rank{$rank}"])) {
                    $allocationPercentage = $allocation["rank{$rank}"] / 100;
                    $confidence = ($token['rank_confidence'] ?? 0) / 100;
                    $betAmount = $baseUnitSize * $allocationPercentage * $confidence;

                    if ($betAmount > 0) {
                        $bets[] = [
                            'symbol' => $token['symbol'],
                            'predicted_rank' => $rank,
                            'bet_amount' => round($betAmount, 2),
                            'confidence' => $token['rank_confidence'] ?? 0,
                            'allocation_percentage' => $allocation["rank{$rank}"]
                        ];
                    }
                }
            }
        }

        return $bets;
    }

    /**
     * 获取当前轮次ID
     */
    private function getCurrentRoundId(): ?string
    {
        try {
            // 这里应该根据你的业务逻辑来获取当前轮次ID
            // 可能从GameRound模型、缓存或其他数据源获取
            $latestRound = \App\Models\GameRound::latest()->first();
            return $latestRound ? $latestRound->round_id : null;
        } catch (\Exception $e) {
            Log::error('获取当前轮次ID失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 执行自动下注（基于当前分析结果）- 返回下注建议给前端
     */
    public function executeAutoBetting(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()?->id ?? 'guest';
            $config = Cache::get("auto_betting_config_{$userId}");

            if (!$config || !$config['enabled']) {
                return response()->json([
                    'success' => false,
                    'message' => '自动下注未启用或配置不存在'
                ], 422);
            }

            // 检查当前状态
            $status = Cache::get("auto_betting_status_{$userId}");
            if (!$status || !$status['is_running']) {
                return response()->json([
                    'success' => false,
                    'message' => '自动下注系统未运行'
                ], 422);
            }

            // 获取当前分析数据
            $currentAnalysis = $this->gamePredictionService->getCurrentRoundPredictions();
            if (empty($currentAnalysis)) {
                return response()->json([
                    'success' => false,
                    'message' => '当前无可用分析数据'
                ], 422);
            }

            // 检查触发条件
            $trigger = $this->checkBettingTriggers($currentAnalysis, $config);
            if (!$trigger['met']) {
                return response()->json([
                    'success' => false,
                    'message' => '当前条件不满足下注要求',
                    'trigger_details' => $trigger['details']
                ], 422);
            }

            // 计算推荐下注
            $recommendedBets = $this->calculateBetAmounts($currentAnalysis, $config);
            if (empty($recommendedBets)) {
                return response()->json([
                    'success' => false,
                    'message' => '无推荐下注方案'
                ], 422);
            }

            // 获取当前轮次ID
            $currentRoundId = $this->getCurrentRoundId();
            if (!$currentRoundId) {
                return response()->json([
                    'success' => false,
                    'message' => '无法获取当前轮次ID'
                ], 422);
            }

            // 返回下注建议给前端，让前端直接调用API
            return response()->json([
                'success' => true,
                'message' => '自动下注条件满足，返回下注建议',
                'data' => [
                    'trigger_details' => $trigger['details'],
                    'recommended_bets' => $recommendedBets,
                    'round_id' => $currentRoundId,
                    'jwt_token' => $config['jwt_token']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('自动下注执行失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return response()->json([
                'success' => false,
                'message' => '自动下注执行失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 记录下注历史
     */
    private function recordBettingHistory(string $userId, array $betData): void
    {
        $history = Cache::get("auto_betting_history_{$userId}", []);
        $history[] = $betData;

        // 只保留最近100条记录
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }

        Cache::put("auto_betting_history_{$userId}", $history, now()->addDays(30));
    }

    /**
     * 执行实际下注
     */
    public function placeBet(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => '请在前端直接调用dojo API进行下注'
        ], 422);
    }

    /**
     * 记录下注结果（由前端调用）
     */
    public function recordBetResult(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'round_id' => 'required|string',
                'token_symbol' => 'required|string',
                'amount' => 'required|numeric',
                'bet_id' => 'required|string',
                'success' => 'required|boolean',
                'result_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = $request->user()?->id ?? 'guest';

            // 记录下注历史
            $this->recordBettingHistory($userId, [
                'round_id' => $request->round_id,
                'token_symbol' => $request->token_symbol,
                'amount' => $request->amount,
                'bet_id' => $request->bet_id,
                'success' => $request->success,
                'result_data' => $request->result_data,
                'placed_at' => now()->toISOString()
            ]);

            // 更新统计
            $status = Cache::get("auto_betting_status_{$userId}", []);
            if ($request->success) {
                $status['total_bets'] = ($status['total_bets'] ?? 0) + 1;
                $status['last_bet_at'] = now()->toISOString();
            }
            Cache::put("auto_betting_status_{$userId}", $status, now()->addDays(1));

            return response()->json([
                'success' => true,
                'message' => '下注记录已保存'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '记录下注结果失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
