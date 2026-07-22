<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiat off-ramp: a withdrawal can now be a cash payout to a bank account or a
 * mobile wallet (bKash/Nagad/…) instead of an on-chain transfer. Crypto
 * withdrawals leave these null and keep using to_address/chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('payout_method', 16)->nullable()->after('to_address'); // bank | mobile (null = crypto)
            $table->json('payout_details')->nullable()->after('payout_method');    // provider, account no/name, etc.
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['payout_method', 'payout_details']);
        });
    }
};
