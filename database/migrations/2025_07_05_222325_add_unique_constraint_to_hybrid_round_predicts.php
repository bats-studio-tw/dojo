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
        Schema::table('hybrid_round_predicts', function (Blueprint $table) {
            // 添加唯一约束：同一个轮次中同一个Token只能有一条记录
            $table->unique(['game_round_id', 'token_symbol'], 'unique_round_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hybrid_round_predicts', function (Blueprint $table) {
            $table->dropUnique('unique_round_token');
        });
    }
};
