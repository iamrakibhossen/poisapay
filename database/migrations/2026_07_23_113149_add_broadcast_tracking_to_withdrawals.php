<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the broadcast nonce, the chain head at broadcast time, and a broadcast
 * attempt counter on a withdrawal. Needed for Replace-By-Fee (re-sign with the SAME
 * nonce + a bumped fee) and for dead-lettering after K attempts. Additive + nullable
 * = zero-downtime, backward compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->unsignedBigInteger('broadcast_nonce')->nullable()->after('onchain_tx_id');
            $table->unsignedBigInteger('broadcast_block')->nullable()->after('broadcast_nonce');
            $table->unsignedInteger('broadcast_attempts')->default(0)->after('broadcast_block');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['broadcast_nonce', 'broadcast_block', 'broadcast_attempts']);
        });
    }
};
