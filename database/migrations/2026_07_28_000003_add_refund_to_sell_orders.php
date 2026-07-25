<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund bookkeeping on the order: when a seller refunds, the buyer is credited
 * back and the seller's net (+ platform commission) is reversed on the ledger.
 * These columns record when and how much was returned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sell_orders', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('earnings_released_at');
            $table->bigInteger('refunded_amount')->nullable()->after('refunded_at'); // minor units returned to buyer
        });
    }

    public function down(): void
    {
        Schema::table('sell_orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_at', 'refunded_amount']);
        });
    }
};
