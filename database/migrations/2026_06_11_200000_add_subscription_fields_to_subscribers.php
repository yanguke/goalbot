<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            // 'free' | 'per_match' | 'full_tournament'
            $table->string('subscription_type')->default('free')->after('is_active');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_type');
            $table->timestamp('paid_at')->nullable()->after('subscription_expires_at');
        });

        // Existing is_active=true rows (created before enforcement) — keep them free
        // so we don't break current users; admin can manually upgrade if needed.
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['subscription_type', 'subscription_expires_at', 'paid_at']);
        });
    }
};
