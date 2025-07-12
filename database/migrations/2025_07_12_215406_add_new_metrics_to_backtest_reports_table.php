<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('backtest_reports', function (Blueprint $table) {
            // 添加新的专业指标字段
            $table->decimal('sortino_ratio', 8, 4)->default(0)->after('sharpe_ratio');
            $table->decimal('calmar_ratio', 8, 4)->default(0)->after('sortino_ratio');
            $table->decimal('volatility', 8, 4)->default(0)->after('profit_rate');
            $table->decimal('profit_factor', 8, 4)->default(0)->after('volatility');
            $table->integer('consecutive_wins')->default(0)->after('profit_factor');
            $table->integer('consecutive_losses')->default(0)->after('consecutive_wins');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_reports', function (Blueprint $table) {
            $table->dropColumn([
                'sortino_ratio',
                'calmar_ratio',
                'volatility',
                'profit_factor',
                'consecutive_wins',
                'consecutive_losses'
            ]);
        });
    }
};
