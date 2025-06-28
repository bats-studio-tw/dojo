<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\GameDataProcessorService;
use App\Services\GamePredictionService;
use App\Models\AutoBettingRecord;
use App\Models\AutoBettingConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Crypt;

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
     * 获取用户统计信息
     */
    public function getUserStats(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'UID不能为空',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uid = $request->uid;
            $userStats = AutoBettingRecord::getUserStats($uid);
            $todayStats = AutoBettingRecord::getTodayStats($uid);

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
            $uid = $request->input('uid');

            if (!$uid) {
                return response()->json([
                    'success' => false,
                    'message' => '需要用户UID'
                ], 422);
            }

            // 使用uid作为唯一标识
            $status = Cache::get("auto_betting_status_{$uid}", [
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
            $userStats = AutoBettingRecord::getUserStats($uid);
            $todayStats = AutoBettingRecord::getTodayStats($uid);

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
                'uid' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uid = $request->uid;
            $action = $request->action;

            if ($action === 'start') {
                // 启动自动下注
                $userStats = AutoBettingRecord::getUserStats($uid);
                $todayStats = AutoBettingRecord::getTodayStats($uid);

                Cache::put("auto_betting_status_{$uid}", [
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
                $status = Cache::get("auto_betting_status_{$uid}", []);
                $status['is_running'] = false;
                $status['stopped_at'] = now()->toISOString();
                Cache::put("auto_betting_status_{$uid}", $status, now()->addDays(1));

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
     * 获取下注历史记录
     */
    public function getBettingHistory(Request $request): JsonResponse
    {
        try {
            $uid = $request->input('uid');

            if (!$uid) {
                return response()->json([
                    'success' => false,
                    'message' => '需要用户UID'
                ], 422);
            }

            // 从数据库获取历史记录
            $history = AutoBettingRecord::where('uid', $uid)
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
     * 执行自动下注（用于前端手动触发）- 接收前端配置参数
     */
    public function executeAutoBetting(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string',
                'config' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uid = $request->uid;
            $config = $request->config;

            if (empty($config['jwt_token'])) {
                return response()->json([
                    'success' => false,
                    'message' => '缺少JWT Token'
                ], 422);
            }

            // 检查当前状态
            $status = Cache::get("auto_betting_status_{$uid}");
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
                    'uid' => $uid
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('自动下注执行失败', [
                'error' => $e->getMessage(),
                'uid' => $uid ?? 'unknown'
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
                'uid' => 'required|string',
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

            $uid = $request->uid;

            // 保存到数据库
            $record = AutoBettingRecord::create([
                'uid' => $uid,
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
            $status = Cache::get("auto_betting_status_{$uid}", []);
            if ($request->success) {
                $userStats = AutoBettingRecord::getUserStats($uid);
                $todayStats = AutoBettingRecord::getTodayStats($uid);

                $status['total_bets'] = $userStats['total_bets'];
                $status['total_profit_loss'] = $userStats['total_profit_loss'];
                $status['today_profit_loss'] = $todayStats['today_profit_loss'];
                $status['last_bet_at'] = now()->toISOString();
            }
            Cache::put("auto_betting_status_{$uid}", $status, now()->addDays(1));

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

    /**
     * 获取用户的自动下注配置
     */
    public function getConfig(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'UID参数是必需的',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uid = $request->input('uid');

            // 获取或创建用户配置
            $config = AutoBettingConfig::getByUid($uid);

            // 解密JWT Token
            $jwt_token = '';
            if ($config->encrypted_jwt_token) {
                try {
                    $jwt_token = Crypt::decryptString($config->encrypted_jwt_token);
                } catch (\Exception $e) {
                    Log::warning('JWT Token解密失败', ['error' => $e->getMessage(), 'uid' => $uid]);
                }
            }

            // 将payload和顶层字段合并后一起返回给前端
            $response_data = array_merge(
                $config->config_payload ?? AutoBettingConfig::getDefaultConfig(),
                [
                    'is_active' => $config->is_active,
                    'jwt_token' => $jwt_token,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $response_data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取配置失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 保存用户的自动下注配置
     */
    public function saveConfig(Request $request): JsonResponse
    {
        try {
            // 验证必要的参数
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string',
                'is_active' => 'required|boolean',
                'jwt_token' => 'present|string|nullable', // 允许jwt_token为空字符串或null
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uid = $request->input('uid');
            $isActive = $request->input('is_active');
            $jwtToken = $request->input('jwt_token');

            // 获取payload数据（除了顶层字段外的所有数据）
            $payload = $request->except(['uid', 'is_active', 'jwt_token']);

            // 加密JWT Token
            $encrypted_jwt = '';
            if (!empty($jwtToken)) {
                $encrypted_jwt = Crypt::encryptString($jwtToken);
            }

            // 更新或创建配置
            AutoBettingConfig::updateOrCreate(
                ['uid' => $uid],
                [
                    'is_active' => $isActive,
                    'encrypted_jwt_token' => $encrypted_jwt,
                    'config_payload' => $payload,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => '配置已成功保存'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '保存配置失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
