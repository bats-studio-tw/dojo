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
        Schema::table('token_ratings', function (Blueprint $table) {
            // 时间衰减相关字段
            $table->decimal('decayed_top3_rate', 5, 2)->nullable()->after('games')->comment('时间衰减的top3胜率');
            $table->decimal('decayed_win_rate', 5, 2)->nullable()->after('decayed_top3_rate')->comment('时间衰减的胜率');
            $table->decimal('decayed_avg_rank', 4, 2)->nullable()->after('decayed_win_rate')->comment('时间衰减的平均排名');
            $table->boolean('decay_applied')->default(false)->after('decayed_avg_rank')->comment('是否应用了时间衰减');
            $table->timestamp('decay_calculated_at')->nullable()->after('decay_applied')->comment('衰减指标计算时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('token_ratings', function (Blueprint $table) {
            $table->dropColumn([
                'decayed_top3_rate',
                'decayed_win_rate',
                'decayed_avg_rank',
                'decay_applied',
                'decay_calculated_at'
            ]);
        });
    }
};
