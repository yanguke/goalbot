<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_visits', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->index();
            $table->string('country', 2)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('version', 10)->nullable(); // A/B test variant
            $table->string('utm_source', 100)->nullable()->index();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable()->index();
            $table->string('utm_term', 100)->nullable();
            $table->string('utm_content', 100)->nullable();
            $table->string('event', 20)->default('view')->index(); // view | cta_click
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('utm_source', 100)->nullable()->after('demo_started_at');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 100)->nullable()->after('utm_medium');
            $table->string('utm_term', 100)->nullable()->after('utm_campaign');
            $table->string('utm_content', 100)->nullable()->after('utm_term');
            $table->string('attribution_ip', 45)->nullable()->after('utm_content');
            $table->string('country', 2)->nullable()->after('attribution_ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_visits');
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'attribution_ip', 'country']);
        });
    }
};
