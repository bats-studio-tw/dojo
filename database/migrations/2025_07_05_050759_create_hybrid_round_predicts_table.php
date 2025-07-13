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
        Schema::create('hybrid_round_predicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_round_id')->index(); // 遊戲回合 ID
            $table->string('token_symbol'); // 代幣符號
            $table->integer('predicted_rank'); // 預測排名
            $table->float('final_score', 8, 4); // 最終得分
            $table->float('elo_prob', 8, 4); // Elo 機率
            $table->float('mom_score', 8, 4)->nullable(); // 動能分數，可能為空
            $table->float('confidence', 8, 4); // 信心度
            $table->timestamps(); // created_at 和 updated_at

            // 可以選擇性地添加外鍵約束，如果 game_rounds 表存在且 game_round_id 是其主鍵
            // $table->foreign('game_round_id')->references('id')->on('game_rounds')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hybrid_round_predicts');
    }
};
