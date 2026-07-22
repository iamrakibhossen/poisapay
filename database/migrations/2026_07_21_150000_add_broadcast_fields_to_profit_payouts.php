<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the instant profit payout trackable like an on-chain send: record the
 * network, destination address, a (simulated on testnet) tx hash, gas and a
 * status. Crypto payouts broadcast → completed; fiat payouts are recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profit_payouts', function (Blueprint $table) {
            $table->string('network', 24)->nullable()->after('destination');            // chain name/key
            $table->string('destination_address', 128)->nullable()->after('network');
            $table->string('status', 16)->default('completed')->after('destination_address');
            $table->string('tx_hash', 128)->nullable()->after('status');
            $table->decimal('gas_fee', 78, 0)->default(0)->after('tx_hash');             // native base units
            $table->timestamp('completed_at')->nullable()->after('gas_fee');
        });
    }

    public function down(): void
    {
        Schema::table('profit_payouts', function (Blueprint $table) {
            $table->dropColumn(['network', 'destination_address', 'status', 'tx_hash', 'gas_fee', 'completed_at']);
        });
    }
};
