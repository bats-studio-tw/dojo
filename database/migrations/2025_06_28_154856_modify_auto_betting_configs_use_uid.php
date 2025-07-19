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
        Schema::table('auto_betting_configs', function (Blueprint $table) {
            // 删除外键约束和user_id字段
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');

            // 添加uid字段作为dojo游戏的用户标识
            $table->string('uid', 100)->after('id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_betting_configs', function (Blueprint $table) {
            // 回滚：删除uid字段，恢复user_id
            $table->dropIndex(['uid']);
            $table->dropColumn('uid');

            // 恢复user_id字段（注意：这需要users表存在）
            $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            $table->index('user_id');
        });
    }
};
