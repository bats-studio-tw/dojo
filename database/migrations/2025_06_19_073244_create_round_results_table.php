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
        Schema::create('round_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_round_id')->constrained('game_rounds')->onDelete('cascade');
            $table->string('token_symbol');
            $table->unsignedTinyInteger('rank');
            $table->decimal('value', 10, 4);
            $table->index(['token_symbol', 'rank']); // 為常用查詢建立索引
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('round_results');
    }
};
