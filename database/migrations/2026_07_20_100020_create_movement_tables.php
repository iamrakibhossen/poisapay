<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-chain money movement (TDD §6): deposit addresses, observed txs,
 * deposits, withdrawals, sweeps, broadcast attempts. NUMERIC(78,0) base units.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('chain_id')->constrained('chains');
            $table->foreignUuid('xpub_id')->constrained('custody_xpubs');
            $table->unsignedBigInteger('derivation_index');
            $table->string('address', 64);
            $table->boolean('is_watched')->default(true);
            $table->timestamps();

            $table->unique(['chain_id', 'address'], 'uq_addr_chain_address');   // §7.2
            $table->unique(['xpub_id', 'derivation_index'], 'uq_addr_xpub_index');
            $table->index('address');
        });

        Schema::create('onchain_txs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('chain_id')->constrained('chains');
            $table->string('tx_hash', 80);
            $table->unsignedInteger('log_index')->default(0);   // token Transfer log slot / vout
            $table->string('from_address', 64)->nullable();
            $table->string('to_address', 64)->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('assets');
            $table->decimal('amount', 78, 0)->default(0);       // base units
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            $table->string('status', 16)->default('detected');
            $table->string('direction', 8)->default('in');      // in | out
            $table->timestamps();

            $table->unique(['chain_id', 'tx_hash', 'log_index'], 'uq_onchain_tx');
            $table->index(['status', 'chain_id']);
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('deposit_address_id')->constrained('deposit_addresses');
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignUuid('onchain_tx_id')->constrained('onchain_txs');
            $table->decimal('amount', 78, 0);                   // base units
            $table->unsignedInteger('confirmations')->default(0);
            $table->unsignedInteger('required_confirmations');
            $table->string('status', 16)->default('detected');
            $table->uuid('credit_entry_id')->nullable();        // journal entry once credited
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            // No double-credit: one deposit per on-chain tx (§7.2).
            $table->unique('onchain_tx_id', 'uq_deposit_onchain_tx');
            $table->foreign('credit_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['user_id', 'status']);
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('to_address', 64);
            $table->decimal('amount', 78, 0);
            $table->decimal('fee', 78, 0)->default(0);
            $table->string('status', 16)->default('pending');
            $table->string('idempotency_key', 160)->unique();   // §7.2
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level', 12)->default('low');
            $table->boolean('requires_review')->default(false);
            $table->uuid('lock_entry_id')->nullable();          // reserve-first lock (A3)
            $table->uuid('settle_entry_id')->nullable();
            $table->uuid('onchain_tx_id')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('admins');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('lock_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('settle_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('onchain_tx_id')->references('id')->on('onchain_txs')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('sweeps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('deposit_address_id')->constrained('deposit_addresses');
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('amount', 78, 0);
            $table->decimal('gas_cost', 78, 0)->default(0);
            $table->string('status', 16)->default('pending');
            $table->string('nonce_context', 80)->nullable();    // idempotent by nonce/context (§7.2)
            $table->uuid('settle_entry_id')->nullable();
            $table->uuid('onchain_tx_id')->nullable();
            $table->timestamps();

            $table->foreign('settle_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('onchain_tx_id')->references('id')->on('onchain_txs')->nullOnDelete();
            $table->unique('nonce_context', 'uq_sweep_nonce_context');
            $table->index('status');
        });

        Schema::create('broadcast_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type', 32);                 // withdrawal | sweep
            $table->uuid('subject_id');
            $table->string('tx_hash', 80)->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->string('outcome', 16)->default('submitted'); // submitted | confirmed | failed
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_attempts');
        Schema::dropIfExists('sweeps');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('onchain_txs');
        Schema::dropIfExists('deposit_addresses');
    }
};
