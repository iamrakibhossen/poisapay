<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sell module — analytics. A cheap append-only raw event stream, rolled up by a
 * background job into per-day aggregates. Dashboards read ONLY the aggregate
 * tables — never the transactional/raw tables — so analytics can never slow
 * orders. The raw table is partition-ready (by month on occurred_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Raw event stream (write-cheap, never read by dashboards) ────────────
        Schema::create('shop_analytics_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->foreignUuid('sales_page_id')->nullable()->constrained('shop_sales_pages')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('shop_orders')->nullOnDelete();
            $table->string('type', 24);                           // page_view|checkout_start|purchase|upsell_view|upsell_accept|upsell_skip
            $table->string('session_id', 64)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('referrer', 255)->nullable();
            $table->jsonb('utm')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            // Rollup job scans by page + time window.
            $table->index(['sales_page_id', 'occurred_at']);
            $table->index(['seller_id', 'type', 'occurred_at']);
            // --- merged: FK indexes / constraints (was a trailing patch migration) ---
            $table->index('order_id');

        });

        // ── Per-day aggregates (what dashboards read) ───────────────────────────
        Schema::create('shop_daily_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->foreignUuid('sales_page_id')->nullable()->constrained('shop_sales_pages')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unsignedBigInteger('checkouts')->default(0);
            $table->unsignedBigInteger('orders')->default(0);
            $table->unsignedBigInteger('upsell_accepts')->default(0);
            $table->bigInteger('revenue_amount')->default(0);     // minor units, seller settlement asset
            $table->timestamps();

            // One aggregate row per (page, day); fast dashboard range scans.
            $table->unique(['sales_page_id', 'day']);
            $table->index(['seller_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_daily_stats');
        Schema::dropIfExists('shop_analytics_events');
    }
};
