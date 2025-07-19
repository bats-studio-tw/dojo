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
        Schema::table('auto_betting_records', function (Blueprint $table) {
            // uid列已经在之前的迁移中添加，这里不需要重复添加

            $table->dropIndex(['wallet_address']);
            $table->dropIndex(['wallet_address', 'created_at']);
            $table->dropIndex(['round_id', 'wallet_address']);
            $table->dropIndex(['wallet_address', 'status']);

            $table->dropColumn('wallet_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_betting_records', function (Blueprint $table) {
            $table->string('wallet_address')->after('uid')->index(); // 钱包地址，用于标识用户

            $table->index(['wallet_address', 'created_at']);
            $table->index(['round_id', 'wallet_address']);
            $table->index(['wallet_address', 'status']);
        });
    }
};
