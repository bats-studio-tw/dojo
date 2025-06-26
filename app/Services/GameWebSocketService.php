<?php

namespace App\Services;

use App\Models\GameRound; // 雖然這裡不用，但你的舊版有，我先保留
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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
    ) {}

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
        $heartbeatTimer = $loop->addPeriodicTimer(60, function() {
            $processedRoundsCount = count($this->processedRounds);
            $this->consoleOutput("💓 WebSocket 监听器运行中... " . date('H:i:s') .
                " | 收到消息: {$this->messageCount} | 结算数据: {$this->settlementCount} | 处理轮次: {$processedRoundsCount}");
        });

        $connect = function() use ($connector, &$connect) {
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
                'reason' => $reason
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
        if (!str_starts_with($payload, 'NF#')) {
            return;
        }

        $this->messageCount++; // 统计所有收到的消息

        try {
            $jsonData = substr($payload, 3);
            $outerData = json_decode($jsonData, true);

            // 检查外层数据结构
            if (!isset($outerData['type']) || $outerData['type'] !== 'gameData') {
                return;
            }

            // 解析内层的游戏数据
            if (!isset($outerData['data'])) {
                return;
            }

            $gameData = json_decode($outerData['data'], true);
            if (!$gameData) {
                $this->consoleOutput("⚠️ 无法解析游戏数据");
                return;
            }

            // 现在检查内层的 type 和 status
            $type = $gameData['type'] ?? 'unknown';
            $status = $gameData['status'] ?? 'unknown';
            $rdId = $gameData['rdId'] ?? 'unknown';

            // *** 核心邏輯：處理不同狀態的遊戲訊息 ***
            if ($type === 'round') {

                if ($status === 'settling' || $status === 'settled') {
                    // 處理結算數據
                    $this->logInfo("🎯 偵測到結算資料！", ['rdId' => $rdId, 'status' => $status]);
                    $this->dataProcessor->processSettlementData($gameData);
                    $this->settlementCount++;
                    $this->consoleOutput("🎯 处理结算数据: {$rdId} (状态: {$status})");
                } elseif ($status === 'bet') {
                    // 检查是否已经处理过这个轮次
                    if (in_array($rdId, $this->processedRounds)) {
                        // 已处理过，跳过
                        return;
                    }

                    // 處理新局開始，將代幣信息存儲到 Redis
                    $this->consoleOutput("🚀 发现新轮次，准备存储代币信息");
                    $this->logInfo("🚀 偵測到新局開始！", ['rdId' => $rdId, 'status' => $status]);

                    // 尝试存储代币信息
                    if ($this->storeCurrentRoundTokens($gameData)) {
                        // 存储成功，记录此轮次ID
                        $this->processedRounds[] = $rdId;
                        $this->consoleOutput("🚀 新局开始: {$rdId} (状态: {$status})");

                        // 异步触发预测分析计算
                        $this->triggerPredictionCalculation($gameData);

                        // 保持数组大小，只保留最近50个轮次记录
                        if (count($this->processedRounds) > 50) {
                            $this->processedRounds = array_slice($this->processedRounds, -50);
                        }
                    }
                } else {
                    // 其他狀態的輪次訊息 - 只记录但不显示，减少输出
                    // $this->consoleOutput("🎲 游戏轮次其他状态: {$status} | ID: {$rdId}");
                }
            } else {
                // 非round类型的消息 - 只记录但不显示，减少输出
                // $this->consoleOutput("📊 非轮次消息 - 类型: {$type} | 状态: {$status}");
            }

        } catch (\Exception $e) {
            $this->logError("❌ 處理 WebSocket 訊息時發生錯誤", [
                'error' => $e->getMessage(),
                'payload' => substr($payload, 0, 200) . '...' // 只记录前200字符避免日志过长
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
                'rdId' => $gameData['rdId'] ?? 'unknown'
            ]);
        }
    }

    /**
     * 將當前局的代幣信息存儲到 Redis
     * @return bool 是否存储成功
     */
        private function storeCurrentRoundTokens(array $gameData): bool
    {
        $roundId = $gameData['rdId'] ?? null;

        if (!$roundId) {
            $this->logWarning('新局數據缺少 rdId', ['gameData' => $gameData]);
            $this->consoleOutput("❌ 新局数据缺少 rdId，退出存储");
            return false;
        }

        try {
            // 檢查是否有代幣數據
            if (!isset($gameData['token'])) {
                $this->logWarning('新局數據中缺少 token 字段', ['rdId' => $roundId]);
                $this->consoleOutput("❌ 游戏数据中缺少 token 字段");
                return false;
            }

            if (!is_array($gameData['token'])) {
                $this->logWarning('新局數據中 token 不是数组格式', ['rdId' => $roundId, 'token_type' => gettype($gameData['token'])]);
                $this->consoleOutput("❌ token 不是数组格式，类型: " . gettype($gameData['token']));
                return false;
            }

            $tokens = array_keys($gameData['token']);
            $tokenCount = count($tokens);

            if ($tokenCount > 0) {
                $currentRoundData = [
                    'round_id' => $roundId,
                    'tokens' => $tokens,
                    'status' => $gameData['status'] ?? 'unknown',
                    'timestamp' => now()->toISOString(),
                    'token_count' => $tokenCount
                ];

                try {
                    // 存儲到緩存，設置過期時間為 20 分钟，确保比预测数据缓存时间更长
                    Cache::put('game:current_round', $currentRoundData, now()->addMinutes(20));

                    // 验证存储是否成功
                    $storedData = Cache::get('game:current_round');
                    if (!$storedData) {
                        $this->consoleOutput("❌ 缓存验证失败，数据可能未正确存储");
                        return false;
                    }
                } catch (\Exception $cacheException) {
                    $this->consoleOutput("❌ 缓存存储异常: " . $cacheException->getMessage());
                    throw $cacheException;
                }

                $this->logInfo("✅ 當前局代幣信息已存儲到緩存", [
                    'rdId' => $roundId,
                    'tokens' => $tokens,
                    'token_count' => $tokenCount
                ]);

                $this->consoleOutput("💾 存储当前局代币: " . implode(', ', $tokens) . " | 轮次: {$roundId}");
                return true; // 存储成功
            } else {
                $this->logWarning('新局數據中沒有代幣信息', ['rdId' => $roundId]);
                $this->consoleOutput("❌ 代币数量为 0，跳过存储");
                return false; // 代币数量为0
            }

        } catch (\Exception $e) {
            $this->logError("❌ 存儲當前局代幣信息到緩存時發生錯誤", [
                'rdId' => $roundId,
                'error' => $e->getMessage()
            ]);
            return false; // 异常情况
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
