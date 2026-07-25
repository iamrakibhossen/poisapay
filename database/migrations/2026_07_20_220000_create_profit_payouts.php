<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Operator profit withdrawals (§5.3) — moves fee income into owner:payout. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profit_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('amount', 78, 0);              // base units withdrawn
            $table->string('destination', 160)->nullable(); // where it was sent (bank / wallet ref)
            $table->string('network', 24)->nullable();
            $table->string('destination_address', 128)->nullable();
            $table->string('status', 16)->default('completed');
            $table->string('tx_hash', 128)->nullable();
            $table->decimal('gas_fee', 78, 0)->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->uuid('entry_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('admins');
            $table->timestamps();

            $table->foreign('entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['asset_id', 'created_at']);
            // --- merged: FK indexes / constraints (was a trailing patch migration) ---
            $table->index('created_by');
            $table->index('entry_id');

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_payouts');
    }
};
