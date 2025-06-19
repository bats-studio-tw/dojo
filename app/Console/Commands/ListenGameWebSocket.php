<?php

namespace App\Console\Commands;

use App\Services\GameWebSocketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ListenGameWebSocket extends Command
{
    /**
     * 你的簽名和描述保持不變
     */
    protected $signature = 'game:listen {--max-runtime=86400 : 最大运行时间（秒），默认24小时}';
    protected $description = '启动游戏 WebSocket 监听器，持续监听游戏数据并处理结算轮次';

    private bool $shouldStop = false;

    public function __construct(
        private GameWebSocketService $webSocketService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info("🚀 启动游戏 WebSocket 监听器...");
        $this->setupSignalHandlers();

        $maxRuntime = (int) $this->option('max-runtime');
        $startTime = time();

        // 使用 Laravel 的定時任務或 Supervisor 來管理重啟，而不是 Command 內的 while 迴圈
        // 這裡我們添加一個定時器來處理最大運行時間
        \React\EventLoop\Loop::addTimer($maxRuntime, function() {
            $this->info("⏰ 达到最大运行时间，停止监听器。");
            $this->gracefulShutdown();
        });

        // 设置控制台输出回调
        $this->webSocketService->setConsoleOutput(function($message, $level = 'info') {
            match($level) {
                'error' => $this->error($message),
                'warn' => $this->warn($message),
                default => $this->info($message)
            };
        });

        try {
            Log::info("游戏WebSocket监听器启动", ['pid' => getmypid(), 'max_runtime' => $maxRuntime]);

            // 直接启动服务，服务内部会处理循环和重连
            $this->webSocketService->startListening();

            $this->info("✅ WebSocket 监听器正常停止。");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ WebSocket 监听器发生致命错误: " . $e->getMessage());
            Log::error("WebSocket监听器致命错误", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function setupSignalHandlers(): void
    {
        // 检查是否为Windows系统
        if (PHP_OS_FAMILY === 'Windows') {
            $this->info("ℹ️ Windows环境下，使用 Ctrl+C 来停止监听器。");
            return;
        }

        if (!function_exists('pcntl_signal')) {
            $this->info("ℹ️ pcntl扩展未安装，跳过信号处理器设置。使用 Ctrl+C 来停止监听器。");
            return;
        }

        $handler = function (int $signal) {
            $this->info("📡 收到信号 " . $signal . "，正在优雅关闭...");
            $this->gracefulShutdown();
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
        pcntl_async_signals(true);

        $this->info("✅ 信号处理器已设置，支持优雅关闭。");
    }

    private function gracefulShutdown(): void
    {
        if ($this->shouldStop) return; // 防止重複執行

        $this->shouldStop = true;
        $this->webSocketService->stopListening();
    }
}
