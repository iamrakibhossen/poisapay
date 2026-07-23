<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production swap hardening (§F2): capture the market (mid) rate and an optional
 * platform fee on the locked quote, and turn a conversion into a self-contained
 * swap record (status, completion time, spread/fee/gross figures, USD notional
 * for velocity limits). All additive + defaulted so existing swap/ramp/card
 * conversions are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fx_quotes', function (Blueprint $table) {
            // Mid-market rate before spread/fee — the "market rate" half of the
            // rate lock (the existing `rate` column stays the effective/locked rate).
            $table->decimal('market_rate', 38, 18)->nullable()->after('rate');
            // Optional explicit platform fee, on top of the spread (0 = none).
            $table->unsignedInteger('fee_bps')->default(0)->after('spread_bps');
        });

        Schema::table('conversions', function (Blueprint $table) {
            $table->string('status', 16)->default('completed')->after('entry_id');
            $table->timestamp('completed_at')->nullable()->after('status');
            // Denormalised financials so a conversion is a complete swap record.
            $table->decimal('spread_amount', 78, 0)->default(0)->after('completed_at');
            $table->decimal('fee_amount', 78, 0)->default(0)->after('spread_amount');
            $table->decimal('gross_amount', 78, 0)->default(0)->after('fee_amount');
            // USD-valued notional (major units) used for per-user daily swap limits.
            $table->decimal('notional_usd', 38, 2)->nullable()->after('gross_amount');
        });
    }

    public function down(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            $table->dropColumn(['status', 'completed_at', 'spread_amount', 'fee_amount', 'gross_amount', 'notional_usd']);
        });

        Schema::table('fx_quotes', function (Blueprint $table) {
            $table->dropColumn(['market_rate', 'fee_bps']);
        });
    }
};
