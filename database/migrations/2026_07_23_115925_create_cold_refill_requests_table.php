<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cold → hot refill request. Cold storage is signed offline (MPC/air-gapped), so this
 * cannot be fully automated: the monitor raises a `requested` row when hot falls below
 * its low-watermark, an operator approves it and signs the move offline, records the
 * broadcast tx hash, and the ledger treasury:cold → treasury:hot is posted only after
 * on-chain confirmation. The lifecycle + audit live here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cold_refill_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('chain_id')->constrained('chains');
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('amount', 78, 0)->default(0);   // ledger base units to refill
            $table->string('status', 16)->default('requested'); // requested | approved | broadcast | settled | cancelled
            $table->string('cold_address', 64)->nullable();  // source (watch-only)
            $table->string('hot_address', 64)->nullable();   // destination
            $table->string('tx_hash', 80)->nullable();       // filled once the operator broadcasts the offline-signed tx
            $table->foreignUuid('approved_by')->nullable()->constrained('admins');
            $table->timestamp('approved_at')->nullable();
            $table->uuid('settle_entry_id')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_refill_requests');
    }
};
