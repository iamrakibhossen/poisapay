<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third gas-wallet tier: the `healthy_threshold` is the balance at/above which the
 * wallet is HEALTHY (silent). Below it the wallet is WARNING (alert, still pays gas)
 * until it drops under `critical_threshold` (blocks withdrawals). The existing
 * `min_threshold` remains the WARNING marker used to escalate the alert wording.
 *
 * Ordering: critical_threshold <= min_threshold (warning) <= healthy_threshold.
 *
 * Backwards compatible: default 0 means "no explicit healthy target", in which case
 * the warning threshold (`min_threshold`) is used as the healthy line — exactly the
 * prior two-tier behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gas_wallets', function (Blueprint $table) {
            $table->decimal('healthy_threshold', 78, 0)->default(0)->after('critical_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('gas_wallets', function (Blueprint $table) {
            $table->dropColumn('healthy_threshold');
        });
    }
};
