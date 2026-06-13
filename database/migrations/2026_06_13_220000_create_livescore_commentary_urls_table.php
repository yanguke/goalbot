<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livescore_commentary_urls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fixture_id')->unique(); // API-Football fixture ID
            $table->string('home_team');
            $table->string('away_team');
            $table->string('livescore_slug'); // e.g. "usa-vs-paraguay/1417927"
            $table->boolean('verified')->default(false); // Whether we've confirmed this URL works
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['verified']);
            $table->index(['home_team', 'away_team']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livescore_commentary_urls');
    }
};
