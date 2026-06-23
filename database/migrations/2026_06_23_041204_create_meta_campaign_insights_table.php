<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meta_campaign_insights', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id')->nullable()->index();
            $table->string('campaign_name');
            $table->date('date_start');
            $table->date('date_stop');
            $table->decimal('spend', 10, 2)->default(0);
            $table->bigInteger('impressions')->default(0);
            $table->bigInteger('clicks')->default(0);
            $table->decimal('ctr', 5, 2)->default(0); // Click-through rate %
            $table->decimal('cpc', 10, 2)->default(0); // Cost per click
            $table->decimal('cpm', 10, 2)->default(0); // Cost per mille
            $table->decimal('frequency', 5, 2)->default(0);
            $table->bigInteger('reach')->default(0);
            $table->string('platform')->nullable(); // facebook, instagram, messenger
            $table->string('device_platform')->nullable(); // desktop, mobile, tablet
            $table->json('actions')->nullable(); // All action types and counts
            $table->json('action_values')->nullable(); // Conversion values
            $table->json('cost_per_action_type')->nullable(); // CPA by action type
            $table->timestamps();

            // Indexes for performance
            $table->index(['campaign_id', 'date_start']);
            $table->index(['date_start', 'platform']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_campaign_insights');
    }
};
