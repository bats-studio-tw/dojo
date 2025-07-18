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
        Schema::create('prediction_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('strategy_name')->unique(); // 添加唯一约束
            $table->string('run_id')->index();
            $table->json('parameters');
            $table->decimal('score', 8, 4);
            $table->enum('status', ['active', 'inactive', 'deprecated'])->default('inactive');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prediction_strategies');
    }
};
