<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('favorite_team')->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('notify_all_matches')->default(false);
            $table->string('timezone')->default('UTC');
            $table->timestamp('last_notification_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
