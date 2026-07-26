<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture the taker's network fingerprint on each order for fraud analysis —
 * shared device/IP between the two parties is a wash-trade signal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_orders', function (Blueprint $table) {
            $table->string('taker_ip', 45)->nullable()->after('meta');
            $table->string('taker_fingerprint', 64)->nullable()->after('taker_ip');

            $table->index('taker_fingerprint', 'ix_p2p_orders_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('p2p_orders', function (Blueprint $table) {
            $table->dropIndex('ix_p2p_orders_fingerprint');
            $table->dropColumn(['taker_ip', 'taker_fingerprint']);
        });
    }
};
