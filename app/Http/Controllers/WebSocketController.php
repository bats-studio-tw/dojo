<?php

namespace App\Http\Controllers;

use App\Events\GameDataUpdated;
use App\Events\PredictionUpdated;
use App\Events\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebSocketController extends Controller
{
    /**
     * 广播游戏数据更新
     */
    public function broadcastGameData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'game_data' => 'required|array',
                'type' => 'string|in:round,settlement,status',
            ]);

            $gameData = $validated['game_data'];
            $type = $validated['type'] ?? 'game_data';

            // 广播事件
            broadcast(new GameDataUpdated($gameData, $type));

            // 缓存最新的游戏数据
            Cache::put('websocket:latest_game_data', [
                'data' => $gameData,
                'type' => $type,
                'timestamp' => now()->toISOString(),
            ], now()->addMinutes(10));

            Log::info('游戏数据广播成功', [
                'type' => $type,
                'data_keys' => array_keys($gameData)
            ]);

            return response()->json([
                'success' => true,
                'message' => '游戏数据广播成功',
                'type' => $type
            ]);

        } catch (\Exception $e) {
            Log::error('广播游戏数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '广播失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 广播预测数据更新
     */
    public function broadcastPrediction(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'prediction_data' => 'required|array',
                'round_id' => 'required|string',
                'type' => 'string|in:prediction,analysis,result',
            ]);

            $predictionData = $validated['prediction_data'];
            $roundId = $validated['round_id'];
            $type = $validated['type'] ?? 'prediction';

            // 广播事件
            broadcast(new PredictionUpdated($predictionData, $roundId, $type, 'websocket'));

            // 缓存最新的预测数据
            Cache::put("websocket:prediction:{$roundId}", [
                'data' => $predictionData,
                'round_id' => $roundId,
                'type' => $type,
                'timestamp' => now()->toISOString(),
            ], now()->addMinutes(20));

            Log::info('预测数据广播成功', [
                'round_id' => $roundId,
                'type' => $type,
                'prediction_keys' => array_keys($predictionData)
            ]);

            return response()->json([
                'success' => true,
                'message' => '预测数据广播成功',
                'round_id' => $roundId,
                'type' => $type
            ]);

        } catch (\Exception $e) {
            Log::error('广播预测数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '广播失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 发送用户通知
     */
    public function sendUserNotification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'message' => 'required|string|max:255',
                'type' => 'string|in:info,success,warning,error',
                'data' => 'array',
            ]);

            $userId = $validated['user_id'];
            $message = $validated['message'];
            $type = $validated['type'] ?? 'info';
            $data = $validated['data'] ?? [];

            // 广播用户通知
            broadcast(new UserNotification($userId, $message, $type, $data));

            Log::info('用户通知发送成功', [
                'user_id' => $userId,
                'type' => $type,
                'message' => $message
            ]);

            return response()->json([
                'success' => true,
                'message' => '通知发送成功',
                'user_id' => $userId,
                'type' => $type
            ]);

        } catch (\Exception $e) {
            Log::error('发送用户通知失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '发送失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取最新的缓存数据
     */
    public function getLatestData(): JsonResponse
    {
        try {
            $latestGameData = Cache::get('websocket:latest_game_data');
            $currentRound = Cache::get('game:current_round');

            return response()->json([
                'success' => true,
                'data' => [
                    'latest_game_data' => $latestGameData,
                    'current_round' => $currentRound,
                    'server_time' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('获取最新数据失败', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取数据失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * WebSocket连接状态检查
     */
    public function connectionStatus(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'status' => 'connected',
                'server_time' => now()->toISOString(),
                'message' => 'WebSocket服务器运行正常'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'WebSocket服务器连接失败: ' . $e->getMessage()
            ], 500);
        }
    }


}
