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
        Schema::create('round_predicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_round_id')->constrained('game_rounds')->onDelete('cascade');
            $table->string('token_symbol');
            $table->unsignedTinyInteger('predicted_rank');
            $table->decimal('prediction_score', 5, 2);
            $table->json('prediction_data')->nullable(); // 存储完整的预测分析数据
            $table->timestamp('predicted_at');

            // 为常用查询建立索引
            $table->index(['game_round_id', 'predicted_rank']);
            $table->index(['token_symbol', 'predicted_rank']);
            $table->index('predicted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_predicts');
    }
};
