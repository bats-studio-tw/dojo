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
        Schema::create('auto_betting_records', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_address')->index(); // 钱包地址，用于标识用户
            $table->string('round_id'); // 游戏轮次ID
            $table->string('token_symbol'); // 下注的代币符号
            $table->decimal('bet_amount', 10, 2); // 下注金额
            $table->string('bet_id')->nullable(); // 下注ID
            $table->boolean('success')->default(false); // 下注是否成功
            $table->json('prediction_data')->nullable(); // 预测数据
            $table->json('result_data')->nullable(); // 下注结果数据
            $table->decimal('profit_loss', 10, 2)->nullable(); // 盈亏金额
            $table->string('status')->default('pending'); // 状态：pending, success, failed, settled
            $table->timestamps();

            // 索引
            $table->index(['wallet_address', 'created_at']);
            $table->index(['round_id', 'wallet_address']);
            $table->index(['wallet_address', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_betting_records');
    }
};
