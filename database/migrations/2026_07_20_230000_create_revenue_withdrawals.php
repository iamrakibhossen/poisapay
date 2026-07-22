<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revenue (company profit) withdrawals with a full approval + broadcast workflow.
 * The revenue "wallet" itself is the fee-income ledger accounts — balances stay
 * derived from ledger_lines; only this workflow state lives here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('amount', 78, 0);              // base units
            $table->decimal('gas_fee', 78, 0)->default(0); // native coin base units
            $table->string('network', 24)->nullable();     // chain key/name
            $table->string('destination_address', 128);
            $table->text('note')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('tx_hash', 128)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->uuid('entry_id')->nullable();          // ledger entry that moved the revenue out
            $table->uuid('reversal_entry_id')->nullable(); // ledger entry that returned it on failure
            $table->string('idempotency_key', 160)->unique();
            $table->foreignUuid('created_by')->nullable()->constrained('admins');
            $table->foreignUuid('approved_by')->nullable()->constrained('admins');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('reversal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_withdrawals');
    }
};
