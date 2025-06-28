<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\GameDataProcessorService;
use App\Services\GamePredictionService;
use App\Models\AutoBettingRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

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
     * 验证钱包地址和JWT Token
     */
    public function validateWallet(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'wallet_address' => 'required|string|max:255',
                'jwt_token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $walletAddress = $request->wallet_address;
            $jwtToken = $request->jwt_token;

            // 简单格式验证
            if (strlen($jwtToken) < 50) {
                return response()->json([
                    'success' => false,
                    'message' => 'JWT Token格式无效'
                ], 422);
            }

            // 获取用户统计信息
            $userStats = AutoBettingRecord::getUserStats($walletAddress);
            $todayStats = AutoBettingRecord::getTodayStats($walletAddress);

            return response()->json([
                'success' => true,
                'message' => '验证成功',
                'data' => [
                    'wallet_address' => $walletAddress,
                    'user_stats' => $userStats,
                    'today_stats' => $todayStats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '验证失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取用户统计信息
     */
    public function getUserStats(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'wallet_address' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '钱包地址不能为空',
                    'errors' => $validator->errors()
                ], 422);
            }

            $walletAddress = $request->wallet_address;
            $userStats = AutoBettingRecord::getUserStats($walletAddress);
            $todayStats = AutoBettingRecord::getTodayStats($walletAddress);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_stats' => $userStats,
                    'today_stats' => $todayStats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取统计信息失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取自动下注状态
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $walletAddress = $request->input('wallet_address');

            if (!$walletAddress) {
                return response()->json([
                    'success' => false,
                    'message' => '需要钱包地址'
                ], 422);
            }

            // 使用钱包地址作为唯一标识
            $status = Cache::get("auto_betting_status_{$walletAddress}", [
                'is_running' => false,
                'current_round_id' => null,
                'last_bet_at' => null,
                'total_bets' => 0,
                'total_profit_loss' => 0,
                'today_profit_loss' => 0,
                'consecutive_losses' => 0,
                'last_error' => null
            ]);

            // 从数据库获取实际的统计数据
            $userStats = AutoBettingRecord::getUserStats($walletAddress);
            $todayStats = AutoBettingRecord::getTodayStats($walletAddress);

            $status['total_bets'] = $userStats['total_bets'];
            $status['total_profit_loss'] = $userStats['total_profit_loss'];
            $status['today_profit_loss'] = $todayStats['today_profit_loss'];

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
                'action' => 'required|in:start,stop',
                'wallet_address' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $walletAddress = $request->wallet_address;
            $action = $request->action;

            if ($action === 'start') {
                // 启动自动下注
                $userStats = AutoBettingRecord::getUserStats($walletAddress);
                $todayStats = AutoBettingRecord::getTodayStats($walletAddress);

                Cache::put("auto_betting_status_{$walletAddress}", [
                    'is_running' => true,
                    'current_round_id' => null,
                    'last_bet_at' => null,
                    'total_bets' => $userStats['total_bets'],
                    'total_profit_loss' => $userStats['total_profit_loss'],
                    'today_profit_loss' => $todayStats['today_profit_loss'],
                    'consecutive_losses' => 0,
                    'last_error' => null,
                    'started_at' => now()->toISOString()
                ], now()->addDays(1));

                $message = '自动下注已启动';
            } else {
                // 停止自动下注
                $status = Cache::get("auto_betting_status_{$walletAddress}", []);
                $status['is_running'] = false;
                $status['stopped_at'] = now()->toISOString();
                Cache::put("auto_betting_status_{$walletAddress}", $status, now()->addDays(1));

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
            $walletAddress = $request->input('wallet_address');

            if (!$walletAddress) {
                return response()->json([
                    'success' => false,
                    'message' => '需要钱包地址'
                ], 422);
            }

            // 从数据库获取历史记录
            $history = AutoBettingRecord::where('wallet_address', $walletAddress)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();

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
     * 执行单次下注模拟（用于测试）- 接收前端配置参数
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

            // 从请求中获取配置参数（由前端传递）
            $config = $request->input('config', []);

            if (empty($config)) {
                return response()->json([
                    'success' => false,
                    'message' => '缺少配置参数'
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
        $confidenceThreshold = $config['confidence_threshold'] ?? 88;
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
        $scoreGapThreshold = $config['score_gap_threshold'] ?? 6.0;
        $scoreGapMet = $scoreGap >= $scoreGapThreshold;
        $details['score_gap'] = [
            'value' => $scoreGap,
            'threshold' => $scoreGapThreshold,
            'met' => $scoreGapMet
        ];
        if (!$scoreGapMet) $allConditionsMet = false;

        // 条件3: 充足的历史数据
        $totalGames = $topToken['total_games'] ?? 0;
        $minGamesThreshold = $config['min_total_games'] ?? 25;
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
        $bankroll = $config['bankroll'] ?? 1000;
        $betAmount = $config['bet_amount'] ?? 200;
        $strategy = $config['strategy'] ?? 'single_bet';

        $bets = [];

        if ($strategy === 'single_bet') {
            // 单点突破策略：只下注预测第一名
            $topToken = $analysisData[0] ?? null;
            if ($topToken) {
                $confidence = $topToken['rank_confidence'] ?? 0;

                $bets[] = [
                    'symbol' => $topToken['symbol'],
                    'predicted_rank' => 1,
                    'bet_amount' => $betAmount,
                    'confidence' => $confidence
                ];
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
     * 执行自动下注（基于前端传递的配置）- 返回下注建议给前端
     */
    public function executeAutoBetting(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'wallet_address' => 'required|string',
                'config' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $walletAddress = $request->wallet_address;
            $config = $request->config;

            if (empty($config['jwt_token'])) {
                return response()->json([
                    'success' => false,
                    'message' => '缺少JWT Token'
                ], 422);
            }

            // 检查当前状态
            $status = Cache::get("auto_betting_status_{$walletAddress}");
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
                    'jwt_token' => $config['jwt_token'],
                    'wallet_address' => $walletAddress
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('自动下注执行失败', [
                'error' => $e->getMessage(),
                'wallet_address' => $walletAddress ?? 'unknown'
            ]);
            return response()->json([
                'success' => false,
                'message' => '自动下注执行失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 记录下注结果（由前端调用）
     */
    public function recordBetResult(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'wallet_address' => 'required|string',
                'round_id' => 'required|string',
                'token_symbol' => 'required|string',
                'amount' => 'required|numeric',
                'bet_id' => 'required|string',
                'success' => 'required|boolean',
                'prediction_data' => 'nullable|array',
                'result_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $walletAddress = $request->wallet_address;

            // 保存到数据库
            $record = AutoBettingRecord::create([
                'wallet_address' => $walletAddress,
                'round_id' => $request->round_id,
                'token_symbol' => $request->token_symbol,
                'bet_amount' => $request->amount,
                'bet_id' => $request->bet_id,
                'success' => $request->success,
                'prediction_data' => $request->prediction_data,
                'result_data' => $request->result_data,
                'status' => $request->success ? 'success' : 'failed'
            ]);

            // 更新缓存中的统计
            $status = Cache::get("auto_betting_status_{$walletAddress}", []);
            if ($request->success) {
                $userStats = AutoBettingRecord::getUserStats($walletAddress);
                $todayStats = AutoBettingRecord::getTodayStats($walletAddress);

                $status['total_bets'] = $userStats['total_bets'];
                $status['total_profit_loss'] = $userStats['total_profit_loss'];
                $status['today_profit_loss'] = $todayStats['today_profit_loss'];
                $status['last_bet_at'] = now()->toISOString();
            }
            Cache::put("auto_betting_status_{$walletAddress}", $status, now()->addDays(1));

            return response()->json([
                'success' => true,
                'message' => '下注记录已保存',
                'data' => $record
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '记录下注结果失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
