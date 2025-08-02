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
        Schema::table('prediction_results', function (Blueprint $table) {
            $table->float('short_term_momentum_score')->nullable()->after('volume_score');
            $table->float('norm_short_term_momentum')->nullable()->after('norm_volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prediction_results', function (Blueprint $table) {
            $table->dropColumn(['short_term_momentum_score', 'norm_short_term_momentum']);
        });
    }
};
