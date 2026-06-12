<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            // 'live' = every commentary entry as it happens (default)
            // 'digest' = one AI summary every 5 minutes
            $table->string('commentary_mode')->default('digest')->after('notify_all_matches');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('commentary_mode');
        });
    }
};
