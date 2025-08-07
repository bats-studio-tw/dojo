<?php

namespace App\Http\Controllers;

use App\Models\AutoBettingConfig;
use App\Models\AutoBettingRecord;
use App\Services\GameDataProcessorService;
use App\Services\GamePredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

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
                'uid' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'UID不能为空',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $uid = $request->uid;
            $userStats = AutoBettingRecord::getUserStats($uid);
            $todayStats = AutoBettingRecord::getTodayStats($uid);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_stats' => $userStats,
                    'today_stats' => $todayStats,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取统计信息失败: '.$e->getMessage(),
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

            if (! $uid) {
                return response()->json([
                    'success' => false,
                    'message' => '需要用户UID',
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
                'last_error' => null,
            ]);

            // 从数据库获取实际的统计数据
            $userStats = AutoBettingRecord::getUserStats($uid);
            $todayStats = AutoBettingRecord::getTodayStats($uid);

            // 添加调试日志
            \Log::info('AutoBetting Status Debug', [
                'uid' => $uid,
                'cached_total_bets' => $status['total_bets'] ?? 'null',
                'database_total_bets' => $userStats['total_bets'],
                'database_successful_bets' => $userStats['successful_bets'],
                'database_total_profit_loss' => $userStats['total_profit_loss'],
                'today_profit_loss' => $todayStats['today_profit_loss'],
                'records_count_from_db' => AutoBettingRecord::where('uid', $uid)->count(),
            ]);

            // 强制使用数据库的真实数据
            $status['total_bets'] = $userStats['total_bets'];
            $status['total_profit_loss'] = $userStats['total_profit_loss'];
            $status['today_profit_loss'] = $todayStats['today_profit_loss'];

            return response()->json([
                'success' => true,
                'data' => $status,
                'debug' => [
                    'uid' => $uid,
                    'database_stats' => $userStats,
                    'today_stats' => $todayStats,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('获取自动下注状态失败', [
                'uid' => $uid ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取状态失败: '.$e->getMessage(),
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
                'uid' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $uid = $request->uid;
            $action = $request->action;

            if ($action === 'start') {
                // 启动自动下注时，从数据库获取最新的统计数据
                $userStats = AutoBettingRecord::getUserStats($uid);
                $todayStats = AutoBettingRecord::getTodayStats($uid);

                \Log::info('启动自动下注 - 统计数据', [
                    'uid' => $uid,
                    'database_total_bets' => $userStats['total_bets'],
                    'database_successful_bets' => $userStats['successful_bets'],
                    'database_total_profit_loss' => $userStats['total_profit_loss'],
                    'today_profit_loss' => $todayStats['today_profit_loss'],
                ]);

                Cache::put("auto_betting_status_{$uid}", [
                    'is_running' => true,
                    'current_round_id' => null,
                    'last_bet_at' => null,
                    'total_bets' => $userStats['total_bets'],
                    'total_profit_loss' => $userStats['total_profit_loss'],
                    'today_profit_loss' => $todayStats['today_profit_loss'],
                    'consecutive_losses' => 0,
                    'last_error' => null,
                    'started_at' => now()->toISOString(),
                ], now()->addDays(1));

                $message = '自动下注已启动';
            } else {
                // 停止自动下注时，保持当前统计数据不变
                $status = Cache::get("auto_betting_status_{$uid}", []);
                $status['is_running'] = false;
                $status['stopped_at'] = now()->toISOString();

                // 确保停止时也有最新的统计数据
                if (! isset($status['total_bets'])) {
                    $userStats = AutoBettingRecord::getUserStats($uid);
                    $todayStats = AutoBettingRecord::getTodayStats($uid);
                    $status['total_bets'] = $userStats['total_bets'];
                    $status['total_profit_loss'] = $userStats['total_profit_loss'];
                    $status['today_profit_loss'] = $todayStats['today_profit_loss'];
                }

                Cache::put("auto_betting_status_{$uid}", $status, now()->addDays(1));

                $message = '自动下注已停止';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            \Log::error('切换自动下注状态失败', [
                'uid' => $uid ?? 'unknown',
                'action' => $action ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '操作失败: '.$e->getMessage(),
            ], 500);
        }
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
                'config' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $uid = $request->uid;
            $config = $request->config;

            if (empty($config['jwt_token'])) {
                return response()->json([
                    'success' => false,
                    'message' => '缺少JWT Token',
                ], 422);
            }

            // 检查当前状态
            $status = Cache::get("auto_betting_status_{$uid}");
            if (! $status || ! $status['is_running']) {
                return response()->json([
                    'success' => false,
                    'message' => '自动下注系统未运行',
                ], 422);
            }

            // 获取当前分析数据
            $currentAnalysis = $this->gamePredictionService->getCurrentRoundPredictions();
            if (empty($currentAnalysis)) {
                return response()->json([
                    'success' => false,
                    'message' => '当前无可用分析数据',
                ], 422);
            }

            // 根据"前端决策，后端代理"架构，决策逻辑已移至前端
            // 后端不再进行触发条件检查和下注金额计算

            // 获取当前轮次ID
            $currentRoundId = $this->getCurrentRoundId();
            if (! $currentRoundId) {
                return response()->json([
                    'success' => false,
                    'message' => '无法获取当前轮次ID',
                ], 422);
            }

            // 返回当前分析数据给前端，让前端进行决策和执行
            return response()->json([
                'success' => true,
                'message' => '返回当前分析数据，请在前端进行决策和下注执行',
                'data' => [
                    'current_analysis' => $currentAnalysis,
                    'round_id' => $currentRoundId,
                    'jwt_token' => $config['jwt_token'],
                    'uid' => $uid,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('自动下注执行失败', [
                'error' => $e->getMessage(),
                'uid' => $uid ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'message' => '自动下注执行失败: '.$e->getMessage(),
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
                'result_data' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
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
                'status' => $request->success ? 'success' : 'failed',
            ]);

            // 每次记录下注结果后都更新缓存中的统计（无论成功失败）
            $status = Cache::get("auto_betting_status_{$uid}", []);

            // 从数据库重新获取最新的统计数据
            $userStats = AutoBettingRecord::getUserStats($uid);
            $todayStats = AutoBettingRecord::getTodayStats($uid);

            // 更新所有统计数据
            $status['total_bets'] = $userStats['total_bets'];
            $status['total_profit_loss'] = $userStats['total_profit_loss'];
            $status['today_profit_loss'] = $todayStats['today_profit_loss'];
            $status['last_bet_at'] = now()->toISOString();

            // 添加调试日志
            \Log::info('记录下注结果并更新缓存', [
                'uid' => $uid,
                'token_symbol' => $request->token_symbol,
                'success' => $request->success,
                'updated_total_bets' => $status['total_bets'],
                'database_total_bets' => $userStats['total_bets'],
                'database_record_count' => AutoBettingRecord::where('uid', $uid)->count(),
            ]);

            Cache::put("auto_betting_status_{$uid}", $status, now()->addDays(1));

            return response()->json([
                'success' => true,
                'message' => '下注记录已保存',
                'data' => $record,
                'updated_stats' => [
                    'total_bets' => $status['total_bets'],
                    'total_profit_loss' => $status['total_profit_loss'],
                    'today_profit_loss' => $status['today_profit_loss'],
                ],
            ]);

        } catch (\Exception $e) {
            \Log::error('记录下注结果失败', [
                'uid' => $uid ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '记录下注结果失败: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 检查指定轮次是否已经下过注（防止重复下注）
     */
    public function checkRoundBet(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'uid' => 'required|string',
                'round_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $uid = $request->input('uid');
            $roundId = $request->input('round_id');

            // 查询该用户在该轮次的下注记录
            $existingBets = AutoBettingRecord::where('uid', $uid)
                ->where('round_id', $roundId)
                ->get();

            $hasBet = $existingBets->count() > 0;
            $betCount = $existingBets->count();
            $successfulBets = $existingBets->where('success', true)->count();

            // 返回详细的检查结果
            return response()->json([
                'success' => true,
                'data' => [
                    'has_bet' => $hasBet,
                    'bet_count' => $betCount,
                    'successful_bets' => $successfulBets,
                    'failed_bets' => $betCount - $successfulBets,
                    'round_id' => $roundId,
                    'uid' => $uid,
                    'bet_records' => $existingBets->map(function ($record) {
                        return [
                            'id' => $record->id,
                            'token_symbol' => $record->token_symbol,
                            'bet_amount' => $record->bet_amount,
                            'success' => $record->success,
                            'status' => $record->status,
                            'created_at' => $record->created_at->toISOString(),
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '检查轮次下注状态失败: '.$e->getMessage(),
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
                'uid' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'UID参数是必需的',
                    'errors' => $validator->errors(),
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
                'data' => $response_data,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取配置失败: '.$e->getMessage(),
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
                'jwt_token' => 'nullable|string', // 允许jwt_token为空或不存在
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $uid = $request->input('uid');
            $isActive = $request->input('is_active');
            $jwtToken = $request->input('jwt_token');

            // 获取payload数据（除了顶层字段外的所有数据）
            $payload = $request->except(['uid', 'is_active', 'jwt_token']);

            // 加密JWT Token
            $encrypted_jwt = '';
            if (! empty($jwtToken)) {
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
                'message' => '配置已成功保存',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '保存配置失败: '.$e->getMessage(),
            ], 500);
        }
    }
}
