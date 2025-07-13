<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backtest_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // 執行回測的用戶
            $table->string('batch_id')->nullable(); // Laravel Job Batch ID
            $table->string('strategy_tag'); // 策略標籤
            $table->json('strategy_config'); // 策略配置快照
            $table->json('param_matrix')->nullable(); // Grid Search參數矩陣
            $table->integer('total_rounds'); // 總回測回合數
            $table->integer('successful_rounds'); // 成功預測回合數

            // 核心績效指標
            $table->decimal('win_rate', 5, 4); // 勝率 (0.0000-1.0000)
            $table->decimal('breakeven_rate', 5, 4); // 保本率
            $table->decimal('sharpe_ratio', 8, 4); // 夏普比率
            $table->decimal('sortino_ratio', 8, 4); // Sortino比率
            $table->decimal('calmar_ratio', 8, 4); // Calmar比率
            $table->decimal('max_drawdown', 8, 4); // 最大回撤
            $table->decimal('max_profit', 10, 4); // 單次最大盈利
            $table->decimal('max_loss', 10, 4); // 單次最大虧損
            $table->decimal('avg_profit_loss_ratio', 8, 4); // 平均盈虧比
            $table->decimal('total_profit', 12, 4); // 總盈利
            $table->decimal('profit_rate', 8, 4); // 平均盈利率
            $table->decimal('volatility', 8, 4); // 波動率
            $table->decimal('profit_factor', 8, 4); // 盈利因子
            $table->integer('consecutive_wins'); // 最大連續勝場
            $table->integer('consecutive_losses'); // 最大連續敗場

            // 狀態和元數據
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable(); // 錯誤信息
            $table->timestamp('started_at')->nullable(); // 開始時間
            $table->timestamp('completed_at')->nullable(); // 完成時間
            $table->timestamps();

            // 索引
            $table->index(['user_id', 'created_at']);
            $table->index(['strategy_tag', 'created_at']);
            $table->index(['batch_id']);
            $table->index(['status']);
            $table->index(['win_rate']); // 用於排序
            $table->index(['sharpe_ratio']); // 用於排序
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_reports');
    }
};
