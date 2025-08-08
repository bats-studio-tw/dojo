<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['numeric', 'probability', 'rank'])->default('numeric');
            $table->enum('default_normalization', ['zscore', 'minmax', 'identity', 'robust'])->default('zscore');
            $table->float('default_weight')->default(0);
            $table->boolean('enabled')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};


