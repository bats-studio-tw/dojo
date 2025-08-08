<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prediction_aggregates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_round_id');
            $table->string('token_symbol', 32);
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->double('final_score')->default(0);
            $table->unsignedInteger('rank')->default(0);
            $table->json('details')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['game_round_id', 'profile_id', 'token_symbol'], 'uniq_round_profile_token');
            $table->index(['game_round_id'], 'idx_round');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_aggregates');
    }
};


