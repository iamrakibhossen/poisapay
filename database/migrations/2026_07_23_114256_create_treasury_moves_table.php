<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A treasury rebalancing move between on-chain wallets (currently hot → cold). Mirrors
 * the sweep record: broadcast is tracked here, and the ledger move
 * (treasury:hot → treasury:cold) is posted only after on-chain confirmation. The
 * `nonce_context` unique key makes each move idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_moves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('chain_id')->constrained('chains');
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('direction', 16)->default('hot_to_cold');
            $table->decimal('amount', 78, 0)->default(0);
            $table->string('status', 16)->default('broadcast'); // broadcast | settled | failed
            $table->string('nonce_context')->unique();
            $table->uuid('onchain_tx_id')->nullable();
            $table->uuid('settle_entry_id')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_moves');
    }
};
