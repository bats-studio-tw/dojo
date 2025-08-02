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
        Schema::table('backtest_results', function (Blueprint $table) {
            // 添加 top3 相关指标字段
            $table->integer('top3_correct_predictions')->default(0)->after('correct_predictions')->comment('命中前三名的预测次数');
            $table->decimal('top3_accuracy', 5, 2)->default(0)->after('weighted_accuracy')->comment('Top3 命中率');
            $table->decimal('top3_weighted_accuracy', 5, 2)->default(0)->after('top3_accuracy')->comment('Top3 加权命中率，考虑时间因素');
            $table->decimal('precision_at_3', 5, 2)->default(0)->after('top3_weighted_accuracy')->comment('Precision@3 指标');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_results', function (Blueprint $table) {
            // 回滚时删除 top3 相关字段
            $table->dropColumn([
                'top3_correct_predictions',
                'top3_accuracy',
                'top3_weighted_accuracy',
                'precision_at_3'
            ]);
        });
    }
};
