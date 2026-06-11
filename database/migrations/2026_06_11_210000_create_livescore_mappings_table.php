<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('livescore_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('fixture_id')->primary();
            $table->string('livescore_id');
            $table->string('home_team');
            $table->string('away_team');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livescore_mappings');
    }
};
