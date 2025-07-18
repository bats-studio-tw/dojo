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
        Schema::create('backtest_results', function (Blueprint $table) {
            $table->id();
            $table->string('run_id')->index(); // 添加索引
            $table->string('params_hash', 64)->index(); // 添加索引
            $table->json('parameters');
            $table->decimal('score', 8, 4)->index(); // 添加索引
            $table->integer('total_games');
            $table->integer('correct_predictions');
            $table->decimal('accuracy', 5, 2);
            $table->decimal('avg_confidence', 5, 2);
            $table->json('detailed_results')->nullable();
            $table->timestamps();

            // 添加唯一约束，防止重复参数组合
            $table->unique(['run_id', 'params_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_results');
    }
};
