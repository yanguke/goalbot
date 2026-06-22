<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_visits', function (Blueprint $table) {
            $table->string('fbclid', 255)->nullable()->after('utm_content');
            $table->string('fbp', 255)->nullable()->after('fbclid');
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('fbclid', 255)->nullable()->after('country');
            $table->string('fbp', 255)->nullable()->after('fbclid');
            // Set once the CAPI signup (Lead) event has been reported, to avoid duplicates.
            $table->timestamp('meta_lead_sent_at')->nullable()->after('fbp');
        });
    }

    public function down(): void
    {
        Schema::table('landing_visits', function (Blueprint $table) {
            $table->dropColumn(['fbclid', 'fbp']);
        });
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['fbclid', 'fbp', 'meta_lead_sent_at']);
        });
    }
};
