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
        Schema::table('backtest_results', function (Blueprint $table) {
            // 在accuracy字段之后添加weighted_accuracy字段
            $table->decimal('weighted_accuracy', 5, 2)->nullable()->after('accuracy')->comment('加權準確率，考慮時間因素');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtest_results', function (Blueprint $table) {
            $table->dropColumn('weighted_accuracy');
        });
    }
};
