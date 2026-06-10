<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained();
            $table->unsignedBigInteger('match_id');
            $table->string('event_type'); // goal, kickoff, halftime, etc
            $table->text('message');
            $table->timestamp('sent_at');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
