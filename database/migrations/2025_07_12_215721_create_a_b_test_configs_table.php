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
        Schema::create('a_b_test_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 測試名稱
            $table->text('description')->nullable(); // 測試描述
            $table->json('strategies'); // 策略配置 {strategy_name => config}
            $table->json('traffic_distribution'); // 流量分配 {strategy_name => percentage}
            $table->timestamp('start_date'); // 開始時間
            $table->timestamp('end_date'); // 結束時間
            $table->enum('status', ['active', 'stopped', 'completed'])->default('active'); // 測試狀態
            $table->unsignedBigInteger('created_by')->nullable(); // 創建者
            $table->timestamps();

            // 索引
            $table->index(['status', 'start_date']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_b_test_configs');
    }
};
