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
        Schema::create('token_prices', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->decimal('price_usd', 20, 8);
            $table->string('currency', 10)->default('usd');
            $table->unsignedBigInteger('minute_timestamp'); // 分钟时间戳
            $table->timestamps();

            // 创建symbol和minute_timestamp的唯一索引
            $table->unique(['symbol', 'minute_timestamp'], 'symbol_minute_unique');
            $table->index('symbol');
            $table->index('minute_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_prices');
    }
};
