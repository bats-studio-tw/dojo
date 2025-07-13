<?php

namespace App\Services;

// 雖然這裡不用，但你的舊版有，我先保留
use App\Events\GameDataUpdated;
use App\Events\NewRoundStarted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ratchet\Client\Connector;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;

class GameWebSocketService
{
    private string $websocketUrl = 'wss://minigamewspush.dojo3.io/';
    private bool $shouldStop = false;
    private $consoleOutput = null;
    private int $messageCount = 0;
    private int $settlementCount = 0;
    private array $processedRounds = []; // 记录已处理的轮次ID

    public function __construct(
        private GameDataProcessorService $dataProcessor,
        private GamePredictionService $predictionService
    ) {
    }

    /**
     * 设置控制台输出回调
     */
    public function setConsoleOutput(callable $callback): void
    {
        $this->consoleOutput = $callback;
    }

    /**
     * 输出到控制台
     */
    private function consoleOutput(string $message, string $level = 'info'): void
    {
        if ($this->consoleOutput) {
            ($this->consoleOutput)($message, $level);
        }
    }

    /**
     * 啟動WebSocket監聽器，這將是一個阻塞操作
     */
    public function startListening(): void
    {
        $this->logInfo("準備啟動 WebSocket 連線...");
        $loop = Loop::get();
        $connector = new Connector($loop);

        // 添加心跳定时器，每60秒显示一次状态
        $heartbeatTimer = $loop->addPeriodicTimer(60, function () {
            $processedRoundsCount = count($this->processedRounds);
            $this->consoleOutput("💓 WebSocket 监听器运行中... " . date('H:i:s') .
                " | 收到消息: {$this->messageCount} | 结算数据: {$this->settlementCount} | 处理轮次: {$processedRoundsCount}");
        });

        $connect = function () use ($connector, &$connect) {
            if ($this->shouldStop) {
                $this->logInfo("監聽器被要求停止，不再進行連線。");

                return;
            }

            $this->consoleOutput("🔄 正在连接到游戏服务器...");

            $connector($this->websocketUrl)
                ->then(function ($conn) {
                    $this->logInfo("✅ WebSocket 連線成功建立！");

                    $conn->on('message', function (MessageInterface $msg) {
                        $this->handleMessage($msg->getPayload());
                    });

                    $conn->on('close', function ($code = null, $reason = null) use (&$connect) {
                        $this->logWarning("🔌 WebSocket 連線關閉", ['code' => $code, 'reason' => $reason]);
                        // 連線關閉後，等待5秒再重連
                        $this->logInfo("🔄 5秒後嘗試重連...");
                        Loop::addTimer(5, $connect);
                    });

                    $conn->send('RG#' . bin2hex(random_bytes(16)));
                    $this->logInfo("已發送初始訊息。");

                }, function (\Exception $e) use (&$connect) {
                    $this->logError("❌ WebSocket 連線失敗", ['error' => $e->getMessage()]);
                    $this->logInfo("🔄 5秒後嘗試重連...");
                    Loop::addTimer(5, $connect);
                });
        };

        // 首次連線
        $connect();

        // 啟動事件循環
        $loop->run();
    }

    /**
    * 連接成功回調
    */
    private function onConnectionOpen(WebSocket $conn): void
    {
        $this->logInfo("✅ WebSocket 連線成功建立！");
        $this->reconnectAttempts = 0; // 重置重連计数器

        // *** 這就是修正的部分 ***
        // 發送初始註冊訊息
        $this->sendInitialMessage($conn);
        // *** 修正結束 ***

        // 監聽消息
        $conn->on('message', function (MessageInterface $msg) {
            $this->handleMessage($msg->getPayload());
        });

        // 監聽連接關閉
        $conn->on('close', function ($code = null, $reason = null) {
            $this->logWarning("🔌 WebSocket 連線關閉", [
                'code' => $code,
                'reason' => $reason,
            ]);
            // 在你的Command中，這個事件會觸發重連邏輯
        });

        // 監聽錯誤
        $conn->on('error', function (\Exception $e) use ($conn) {
            $this->logError("❌ WebSocket 連接錯誤", ['error' => $e->getMessage()]);
            $conn->close(); // 觸發 close 事件來處理重連
        });
    }

    /**
     * 發送初始註冊訊息
     * @param WebSocket $conn
     */
    private function sendInitialMessage(WebSocket $conn): void
    {
        // 這段 PHP 程式碼等同於你提供的 JavaScript 函數，用於生成一個唯一的客戶端ID
        $clientId = 'RG#' . bin2hex(random_bytes(16));

        try {
            $conn->send($clientId);
            $this->logInfo("🚀 已發送初始訊息", ['clientId' => $clientId]);
        } catch (\Exception $e) {
            $this->logError("❌ 發送初始訊息失敗", ['error' => $e->getMessage()]);
        }
    }

    /**
     * 停止監聽 (由 Command 呼叫)
     */
    public function stopListening(): void
    {
        $this->shouldStop = true;
        // 如果事件循環正在運行，停止它
        Loop::stop();
        $this->logInfo("🛑 已請求停止 WebSocket 監聽器。");
    }

    /**
     * 處理接收到的消息
     */
    private function handleMessage(string $payload): void
    {
        if (! str_starts_with($payload, 'NF#')) {
            return;
        }

        $this->messageCount++; // 统计所有收到的消息

        try {
            $jsonData = substr($payload, 3);
            $outerData = json_decode($jsonData, true);

            // 检查外层数据结构
            if (! isset($outerData['type']) || $outerData['type'] !== 'gameData') {
                return;
            }

            // 解析内层的游戏数据
            if (! isset($outerData['data'])) {
                return;
            }

            $gameData = json_decode($outerData['data'], true);
            if (! $gameData) {
                $this->consoleOutput("⚠️ 无法解析游戏数据");

                return;
            }

            // 现在检查内层的 type 和 status
            $type = $gameData['type'] ?? 'unknown';
            $status = $gameData['status'] ?? 'unknown';
            $rdId = $gameData['rdId'] ?? 'unknown';

            // *** 核心邏輯：處理不同狀態的遊戲訊息 ***
            if ($type === 'round') {
                // 所有轮次消息都更新当前轮次缓存
                $this->storeCurrentRoundTokens($gameData);
                $this->consoleOutput("📊 更新轮次状态: {$rdId} (状态: {$status})");

                if ($status === 'settling' || $status === 'settled') {
                    // 處理結算數據
                    $this->logInfo("🎯 偵測到結算資料！", ['rdId' => $rdId, 'status' => $status]);
                    $this->dataProcessor->processSettlementData($gameData);
                    $this->settlementCount++;
                    $this->consoleOutput("🎯 处理结算数据: {$rdId} (状态: {$status})");
                } elseif ($status === 'bet') {
                    // 检查是否已经处理过这个轮次的预测
                    if (in_array($rdId, $this->processedRounds)) {
                        // 已处理过预测，跳过
                        return;
                    }

                    // 處理新局開始的特殊逻辑
                    $this->consoleOutput("🚀 发现新轮次，开始预测计算");
                    $this->logInfo("🚀 偵測到新局開始！", ['rdId' => $rdId, 'status' => $status]);

                    // 记录此轮次ID（用于预测计算去重）
                    $this->processedRounds[] = $rdId;
                    $this->consoleOutput("🚀 新局开始: {$rdId} (状态: {$status})");

                    // 触发 NewRoundStarted 事件
                    $this->triggerNewRoundStarted($gameData);

                    // 异步触发预测分析计算（保持原有逻辑作为备用）
                    $this->triggerPredictionCalculation($gameData);

                    // 广播新轮次开始的游戏数据到前端
                    try {
                        broadcast(new GameDataUpdated($gameData, 'bet'));
                        $this->logInfo("📡 新轮次开始数据已广播到WebSocket客户端", [
                            'rdId' => $rdId,
                            'status' => $status,
                        ]);
                    } catch (\Exception $broadcastError) {
                        $this->logError("广播新轮次开始数据失败", [
                            'rdId' => $rdId,
                            'error' => $broadcastError->getMessage(),
                        ]);
                    }

                    // 保持数组大小，只保留最近50个轮次记录
                    if (count($this->processedRounds) > 50) {
                        $this->processedRounds = array_slice($this->processedRounds, -50);
                    }
                } else {
                    // 其他狀態的輪次訊息 - 也需要广播到前端
                    $this->consoleOutput("🎲 游戏轮次状态: {$status} | ID: {$rdId}");

                    // 广播其他状态的游戏数据到前端 (如倒计时、等待等状态)
                    try {
                        broadcast(new GameDataUpdated($gameData, $status));
                        $this->logInfo("📡 游戏状态数据已广播到WebSocket客户端", [
                            'rdId' => $rdId,
                            'status' => $status,
                        ]);
                    } catch (\Exception $broadcastError) {
                        $this->logError("广播游戏状态数据失败", [
                            'rdId' => $rdId,
                            'status' => $status,
                            'error' => $broadcastError->getMessage(),
                        ]);
                    }
                }
            } else {
                // 非round类型的消息 - 只记录但不显示，减少输出
                // $this->consoleOutput("📊 非轮次消息 - 类型: {$type} | 状态: {$status}");
            }

        } catch (\Exception $e) {
            $this->logError("❌ 處理 WebSocket 訊息時發生錯誤", [
                'error' => $e->getMessage(),
                'payload' => substr($payload, 0, 200) . '...', // 只记录前200字符避免日志过长
            ]);
        }
    }

    /**
     * 触发新轮次开始事件
     */
    private function triggerNewRoundStarted(array $gameData): void
    {
        try {
            $roundId = $gameData['rdId'] ?? 'unknown';
            $tokens = array_keys($gameData['token'] ?? []);
            $chainId = 'ethereum'; // 默认链ID，可以根据实际需要调整

            Log::channel('websocket')->info('🎯 开始触发新轮次开始事件', [
                'round_id' => $roundId,
                'tokens_count' => count($tokens),
                'tokens' => $tokens,
                'chain_id' => $chainId,
                'game_data_keys' => array_keys($gameData),
            ]);

            if (empty($tokens)) {
                Log::channel('websocket')->warning('⚠️ 没有代币信息，跳过新轮次事件触发', [
                    'round_id' => $roundId,
                    'game_data' => $gameData,
                ]);
                $this->consoleOutput("⚠️ 没有代币信息，跳过新轮次事件触发");

                return;
            }

            // 验证代币数据格式
            foreach ($tokens as $token) {
                if (empty($token) || ! is_string($token)) {
                    Log::channel('websocket')->error('❌ 代币数据格式无效', [
                        'round_id' => $roundId,
                        'invalid_token' => $token,
                        'token_type' => gettype($token),
                    ]);

                    return;
                }
            }

            Log::channel('websocket')->info('✅ 代币数据验证通过，准备触发事件', [
                'round_id' => $roundId,
                'valid_tokens' => $tokens,
            ]);

            $this->consoleOutput("🎯 触发新轮次开始事件...");

            // 触发 NewRoundStarted 事件
            $event = new NewRoundStarted($roundId, $tokens, $chainId);

            Log::channel('websocket')->info('📡 准备广播 NewRoundStarted 事件', [
                'round_id' => $roundId,
                'event_class' => get_class($event),
                'event_data' => [
                    'roundId' => $event->roundId,
                    'symbols' => $event->symbols,
                    'chainId' => $event->chainId,
                ],
            ]);

            event($event);

            Log::channel('websocket')->info('✅ NewRoundStarted 事件已触发', [
                'round_id' => $roundId,
                'event_dispatched' => true,
                'timestamp' => now()->toISOString(),
            ]);

            $this->consoleOutput("✅ 新轮次开始事件已触发");

        } catch (\Exception $e) {
            Log::channel('websocket')->error('❌ 触发新轮次开始事件异常', [
                'round_id' => $gameData['rdId'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'game_data' => $gameData,
            ]);

            $this->logError("触发新轮次开始事件异常", [
                'error' => $e->getMessage(),
                'rdId' => $gameData['rdId'] ?? 'unknown',
            ]);
        }
    }

    /**
     * 异步触发预测分析计算
     */
    private function triggerPredictionCalculation(array $gameData): void
    {
        try {
            $roundId = $gameData['rdId'] ?? 'unknown';
            $tokens = array_keys($gameData['token'] ?? []);

            if (empty($tokens)) {
                $this->consoleOutput("⚠️ 没有代币信息，跳过预测计算");

                return;
            }

            $this->consoleOutput("🧠 开始计算预测分析...");

            // 在后台异步生成预测数据
            $success = $this->predictionService->generateAndCachePrediction($tokens, $roundId);

            if ($success) {
                $this->consoleOutput("✅ 预测分析已完成并缓存");
            } else {
                $this->consoleOutput("❌ 预测分析计算失败");
            }

        } catch (\Exception $e) {
            $this->logError("预测分析计算异常", [
                'error' => $e->getMessage(),
                'rdId' => $gameData['rdId'] ?? 'unknown',
            ]);
        }
    }

    /**
     * 將當前局的游戏信息存儲到 Redis
     */
    private function storeCurrentRoundTokens(array $gameData): void
    {
        $roundId = $gameData['rdId'] ?? null;
        $status = $gameData['status'] ?? 'unknown';

        if (! $roundId) {
            $this->logWarning('游戏数据缺少 rdId', ['gameData' => $gameData]);

            return;
        }

        try {
            // 准备基础轮次数据
            $currentRoundData = [
                'round_id' => $roundId,
                'status' => $status,
                'timestamp' => now()->toISOString(),
                'tokens' => [],
                'token_count' => 0,
            ];

            // 检查并处理代币数据（并非所有状态都有代币信息）
            if (isset($gameData['token']) && is_array($gameData['token'])) {
                $tokens = array_keys($gameData['token']);
                $tokenCount = count($tokens);

                $currentRoundData['tokens'] = $tokens;
                $currentRoundData['token_count'] = $tokenCount;

                if ($tokenCount > 0) {
                    $this->consoleOutput("💾 更新代币信息: " . implode(', ', $tokens) . " | 轮次: {$roundId} | 状态: {$status}");
                }
            }

            // 存储其他游戏数据
            if (isset($gameData['time'])) {
                $currentRoundData['game_time'] = $gameData['time'];
            }

            if (isset($gameData['result'])) {
                $currentRoundData['result'] = $gameData['result'];
            }

            // 存儲到緩存，設置過期時間為 20 分钟
            Cache::put('game:current_round', $currentRoundData, now()->addMinutes(20));

            $this->logInfo("✅ 當前局游戏信息已更新到緩存", [
                'rdId' => $roundId,
                'status' => $status,
                'token_count' => $currentRoundData['token_count'],
            ]);

        } catch (\Exception $e) {
            $this->logError("❌ 存儲當前局游戏信息到緩存時發生錯誤", [
                'rdId' => $roundId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // 為了簡潔，將日誌記錄方法統一管理
    private function logInfo(string $message, array $context = []): void
    {
        Log::channel('websocket')->info($message, $context);
        // 重要的连接和处理信息也输出到控制台
        if (str_contains($message, '連線') || str_contains($message, '连线') ||
            str_contains($message, '結算') || str_contains($message, '结算') ||
            str_contains($message, '發送') || str_contains($message, '发送') ||
            str_contains($message, 'WebSocket') || str_contains($message, '準備') ||
            str_contains($message, '重連') || str_contains($message, '重连')) {
            $this->consoleOutput($message);
        }
    }

    private function logWarning(string $message, array $context = []): void
    {
        Log::channel('websocket')->warning($message, $context);
        $this->consoleOutput($message, 'warn');
    }

    private function logError(string $message, array $context = []): void
    {
        Log::channel('websocket')->error($message, $context);
        $this->consoleOutput($message, 'error');
    }
}
