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
        Schema::create('a_b_test_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ab_test_id'); // A/B測試ID
            $table->string('strategy'); // 使用的策略
            $table->json('prediction_data'); // 預測數據
            $table->json('actual_result')->nullable(); // 實際結果
            $table->unsignedBigInteger('user_id')->nullable(); // 用戶ID
            $table->string('round_id')->nullable(); // 遊戲回合ID
            $table->boolean('is_correct')->default(false); // 預測是否正確
            $table->timestamps();

            // 索引
            $table->index(['ab_test_id', 'strategy']);
            $table->index(['ab_test_id', 'created_at']);
            $table->index(['strategy', 'is_correct']);
            $table->index('user_id');
            $table->index('round_id');

            // 外鍵約束
            $table->foreign('ab_test_id')->references('id')->on('a_b_test_configs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_b_test_results');
    }
};
