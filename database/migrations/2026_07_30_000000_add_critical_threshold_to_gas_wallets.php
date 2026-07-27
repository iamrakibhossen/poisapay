<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Second gas-wallet alert tier. The existing `min_threshold` remains the WARNING
 * level (top-up soon, alert only); `critical_threshold` is the level below which
 * the wallet cannot realistically pay network gas, escalating the alert and
 * holding on-chain withdrawals for that chain until it is refilled.
 *
 * Backwards compatible: default 0 means "no critical tier", so existing rows keep
 * warning-only behaviour and never block withdrawals until an operator sets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gas_wallets', function (Blueprint $table) {
            $table->decimal('critical_threshold', 78, 0)->default(0)->after('min_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('gas_wallets', function (Blueprint $table) {
            $table->dropColumn('critical_threshold');
        });
    }
};
