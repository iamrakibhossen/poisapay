<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the per-ad daily-volume rollup used to enforce an ad's `daily_limit`
 * (AdOrderGuard::assertWithinAdDailyLimit) — a range scan over one ad's orders
 * for the current day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_orders', function (Blueprint $table) {
            $table->index(['ad_id', 'created_at'], 'ix_p2p_orders_ad_day');
        });
    }

    public function down(): void
    {
        Schema::table('p2p_orders', function (Blueprint $table) {
            $table->dropIndex('ix_p2p_orders_ad_day');
        });
    }
};
