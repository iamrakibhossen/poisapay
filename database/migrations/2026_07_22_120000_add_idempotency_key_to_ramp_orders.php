<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Off-ramp client idempotency (§F1.3). A nullable, unique client key lets the
 * off-ramp request collapse safely on retry — mirroring transfers/withdrawals.
 * Nullable so existing on-ramp rows (and PSP-driven flows) are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ramp_orders', function (Blueprint $table) {
            $table->string('idempotency_key', 160)->nullable()->after('provider_ref');
            $table->unique('idempotency_key', 'uq_ramp_idempotency');
        });
    }

    public function down(): void
    {
        Schema::table('ramp_orders', function (Blueprint $table) {
            $table->dropUnique('uq_ramp_idempotency');
            $table->dropColumn('idempotency_key');
        });
    }
};
