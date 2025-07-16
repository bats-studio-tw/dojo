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

    // 🔧 新增：连接健康检查相关属性
    private ?int $lastMessageTime = null;
    private ?int $lastHeartbeatTime = null;
    private bool $isConnected = false;
    private int $reconnectAttempts = 0;
    private int $maxReconnectAttempts = 3; // 减少重连次数，适配Daemon环境
    private int $reconnectDelay = 5; // 初始重连延迟（秒）
    private int $maxReconnectDelay = 60; // 减少最大重连延迟
    private $connection = null;
    private $healthCheckTimer = null;
    private $heartbeatTimer = null;
    private bool $isDaemonMode = true; // 标记为Daemon模式

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

        // 🔧 新增：健康检查定时器，每30秒检查一次连接状态
        $this->healthCheckTimer = $loop->addPeriodicTimer(30, function () {
            $this->performHealthCheck();
        });

        // 🔧 新增：心跳定时器，每60秒显示一次状态
        $this->heartbeatTimer = $loop->addPeriodicTimer(60, function () {
            $this->performHeartbeat();
        });

        $connect = function () use ($connector, &$connect) {
            if ($this->shouldStop) {
                $this->logInfo("監聽器被要求停止，不再進行連線。");
                return;
            }

            $this->consoleOutput("🔄 正在连接到游戏服务器... (尝试 #" . ($this->reconnectAttempts + 1) . ")");

            $connector($this->websocketUrl)
                ->then(function ($conn) use (&$connect) {
                    $this->connection = $conn;
                    $this->isConnected = true;
                    $this->reconnectAttempts = 0; // 重置重连计数器
                    $this->lastMessageTime = time();
                    $this->lastHeartbeatTime = time();

                    $this->logInfo("✅ WebSocket 連線成功建立！");

                    $conn->on('message', function (MessageInterface $msg) {
                        $this->lastMessageTime = time(); // 更新最后消息时间
                        $this->handleMessage($msg->getPayload());
                    });

                    $conn->on('close', function ($code = null, $reason = null) use (&$connect) {
                        $this->handleConnectionClose($code, $reason, $connect);
                    });

                    $conn->on('error', function (\Exception $e) use ($conn) {
                        $this->logError("❌ WebSocket 連接錯誤", ['error' => $e->getMessage()]);
                        $this->isConnected = false;
                        $conn->close(); // 觸發 close 事件來處理重連
                    });

                    // 发送初始注册消息
                    $this->sendInitialMessage($conn);

                }, function (\Exception $e) use (&$connect) {
                    $this->handleConnectionError($e, $connect);
                });
        };

        // 首次連線
        $connect();

        // 啟動事件循環
        $loop->run();
    }

    /**
     * 🔧 新增：处理连接关闭
     */
    private function handleConnectionClose($code, $reason, $connect): void
    {
        $this->isConnected = false;
        $this->connection = null;

        $this->logWarning("🔌 WebSocket 連線關閉", [
            'code' => $code,
            'reason' => $reason,
            'reconnect_attempts' => $this->reconnectAttempts,
            'is_daemon_mode' => $this->isDaemonMode
        ]);

        if ($this->shouldStop) {
            $this->logInfo("監聽器被要求停止，不再重連。");
            return;
        }

        // 在Daemon模式下，如果重连次数达到上限，主动退出让Daemon重启
        if ($this->isDaemonMode && $this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->logError("🔄 Daemon模式：重连次数已达上限({$this->maxReconnectAttempts})，主动退出让Daemon重启", [
                'reconnect_attempts' => $this->reconnectAttempts,
                'max_attempts' => $this->maxReconnectAttempts,
                'exit_code' => 1
            ]);

            // 清理资源
            $this->cleanup();

            // 主动退出进程，让Daemon重启
            exit(1);
        }

        // 计算重连延迟（指数退避）
        $delay = min($this->reconnectDelay * pow(2, $this->reconnectAttempts), $this->maxReconnectDelay);

        $this->logInfo("🔄 {$delay}秒後嘗試重連... (尝试 #" . ($this->reconnectAttempts + 1) . ")");
        Loop::addTimer($delay, $connect);
    }

    /**
     * 🔧 新增：处理连接错误
     */
    private function handleConnectionError(\Exception $e, $connect): void
    {
        $this->isConnected = false;
        $this->connection = null;

        $this->logError("❌ WebSocket 連線失敗", [
            'error' => $e->getMessage(),
            'reconnect_attempts' => $this->reconnectAttempts,
            'is_daemon_mode' => $this->isDaemonMode
        ]);

        if ($this->shouldStop) {
            $this->logInfo("監聽器被要求停止，不再重連。");
            return;
        }

        $this->reconnectAttempts++;

        // 在Daemon模式下，如果重连次数达到上限，主动退出让Daemon重启
        if ($this->isDaemonMode && $this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->logError("🔄 Daemon模式：重连次数已达上限({$this->maxReconnectAttempts})，主动退出让Daemon重启", [
                'reconnect_attempts' => $this->reconnectAttempts,
                'max_attempts' => $this->maxReconnectAttempts,
                'exit_code' => 1
            ]);

            // 清理资源
            $this->cleanup();

            // 主动退出进程，让Daemon重启
            exit(1);
        }

        // 如果重连次数过多，增加延迟
        if ($this->reconnectAttempts > $this->maxReconnectAttempts) {
            $this->logWarning("⚠️ 重连次数过多，使用最大延迟", [
                'attempts' => $this->reconnectAttempts,
                'max_attempts' => $this->maxReconnectAttempts
            ]);
        }

        // 计算重连延迟（指数退避）
        $delay = min($this->reconnectDelay * pow(2, $this->reconnectAttempts), $this->maxReconnectDelay);

        $this->logInfo("🔄 {$delay}秒後嘗試重連... (尝试 #" . ($this->reconnectAttempts + 1) . ")");
        Loop::addTimer($delay, $connect);
    }

    /**
     * 🔧 新增：执行健康检查
     */
    private function performHealthCheck(): void
    {
        $currentTime = time();

        // 检查是否长时间没有收到消息（超过2分钟）
        if ($this->lastMessageTime && ($currentTime - $this->lastMessageTime) > 120) {
            $this->logWarning("⚠️ 连接可能已断开 - 超过2分钟未收到消息", [
                'last_message_time' => date('Y-m-d H:i:s', $this->lastMessageTime),
                'time_since_last_message' => $currentTime - $this->lastMessageTime . '秒',
                'is_daemon_mode' => $this->isDaemonMode
            ]);

            // 在Daemon模式下，如果长时间无消息，主动退出让Daemon重启
            if ($this->isDaemonMode) {
                $this->logError("🔄 Daemon模式：长时间无消息，主动退出让Daemon重启", [
                    'time_since_last_message' => $currentTime - $this->lastMessageTime . '秒',
                    'exit_code' => 2
                ]);

                // 清理资源
                $this->cleanup();

                // 主动退出进程，让Daemon重启
                exit(2);
            }

            // 如果连接对象存在但长时间无消息，主动关闭重连
            if ($this->connection && $this->isConnected) {
                $this->logInfo("🔄 主动关闭连接以触发重连...");
                $this->connection->close();
            }
        }

        // 检查连接状态
        if (!$this->isConnected && !$this->shouldStop) {
            $this->logWarning("⚠️ 连接状态异常 - 标记为未连接但未停止监听", [
                'is_connected' => $this->isConnected,
                'should_stop' => $this->shouldStop,
                'reconnect_attempts' => $this->reconnectAttempts,
                'is_daemon_mode' => $this->isDaemonMode
            ]);
        }
    }

    /**
     * 🔧 新增：执行心跳检查
     */
    private function performHeartbeat(): void
    {
        $currentTime = time();
        $processedRoundsCount = count($this->processedRounds);

        // 计算时间差
        $timeSinceLastMessage = $this->lastMessageTime ? ($currentTime - $this->lastMessageTime) : 'N/A';
        $timeSinceLastHeartbeat = $this->lastHeartbeatTime ? ($currentTime - $this->lastHeartbeatTime) : 'N/A';

        $statusMessage = "💓 WebSocket 监听器运行中... " . date('H:i:s') .
            " | 连接状态: " . ($this->isConnected ? '✅ 已连接' : '❌ 未连接') .
            " | 收到消息: {$this->messageCount}" .
            " | 结算数据: {$this->settlementCount}" .
            " | 处理轮次: {$processedRoundsCount}" .
            " | 最后消息: " . ($timeSinceLastMessage === 'N/A' ? 'N/A' : $timeSinceLastMessage . '秒前') .
            " | 重连次数: {$this->reconnectAttempts}";

        $this->consoleOutput($statusMessage);
        $this->lastHeartbeatTime = $currentTime;

        // 记录到日志
        $this->logInfo("心跳检查", [
            'is_connected' => $this->isConnected,
            'message_count' => $this->messageCount,
            'settlement_count' => $this->settlementCount,
            'processed_rounds' => $processedRoundsCount,
            'time_since_last_message' => $timeSinceLastMessage,
            'reconnect_attempts' => $this->reconnectAttempts
        ]);
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
     * 🔧 新增：清理资源
     */
    private function cleanup(): void
    {
        $this->shouldStop = true;
        $this->isConnected = false;

        // 清理定时器
        if ($this->healthCheckTimer) {
            Loop::cancelTimer($this->healthCheckTimer);
            $this->healthCheckTimer = null;
        }
        if ($this->heartbeatTimer) {
            Loop::cancelTimer($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }

        // 关闭连接
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }

        $this->logInfo("🧹 资源清理完成");
    }

    /**
     * 停止監聽 (由 Command 呼叫)
     */
    public function stopListening(): void
    {
        $this->cleanup();

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
