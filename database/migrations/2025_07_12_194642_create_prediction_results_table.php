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
        Schema::create('prediction_results', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('game_round_id');
            $table->string('token');
            $table->integer('predict_rank');
            $table->float('predict_score');
            $table->float('elo_score')->nullable();
            $table->float('momentum_score')->nullable();
            $table->float('volume_score')->nullable();
            $table->float('norm_elo')->nullable();
            $table->float('norm_momentum')->nullable();
            $table->float('norm_volume')->nullable();
            $table->json('used_weights');
            $table->json('used_normalization');
            $table->string('strategy_tag');
            $table->json('config_snapshot')->nullable();
            $table->timestamps();

            // 索引
            $table->index(['game_round_id', 'token']);
            $table->index('strategy_tag');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prediction_results');
    }
};
