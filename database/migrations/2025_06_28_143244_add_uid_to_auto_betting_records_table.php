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
        Schema::table('auto_betting_records', function (Blueprint $table) {
            $table->string('uid')->nullable()->after('id')->index(); // 用户唯一标识符
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_betting_records', function (Blueprint $table) {
            $table->dropColumn('uid');
        });
    }
};
