<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feature_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_round_id');
            $table->string('token_symbol', 32);
            $table->string('feature_key');
            $table->double('raw_value')->nullable();
            $table->double('normalized_value')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['game_round_id', 'token_symbol', 'feature_key'], 'uniq_round_token_feature');
            $table->index(['game_round_id'], 'idx_round');
            $table->index(['feature_key', 'game_round_id'], 'idx_feature_round');
            $table->index(['token_symbol', 'game_round_id'], 'idx_token_round');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_snapshots');
    }
};


