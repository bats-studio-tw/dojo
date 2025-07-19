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
        Schema::create('auto_betting_configs', function (Blueprint $table) {
            $table->id();
            // 关键字段1: 用户关联，必须有索引以利查询
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // 关键字段2: 总开关，布尔值且建立索引，方便后端快速筛选活跃用户
            $table->boolean('is_active')->default(false)->index();
            // 核心字段: 使用 json 类型储存所有前端传来的动态参数
            $table->json('config_payload');
            // 独立字段: 敏感信息独立储存并加密
            $table->text('encrypted_jwt_token')->nullable();
            $table->timestamps();

            // 为user_id创建索引，确保查询性能
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_betting_configs');
    }
};
