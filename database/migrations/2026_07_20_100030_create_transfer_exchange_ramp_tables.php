<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Send money (§F4), exchange/conversion (§F2), fiat ramps + spending priority (§F1). */
return new class extends Migration
{
    public function up(): void
    {
        // §F4.4
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->nullable()->constrained('users');
            $table->string('recipient_handle', 128)->nullable(); // off-platform claimable
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('amount', 78, 0);
            $table->string('kind', 16)->default('internal');     // internal | payout | remittance
            $table->string('status', 16)->default('completed');
            $table->uuid('entry_id')->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->string('memo', 140)->nullable();
            $table->timestamp('expires_at')->nullable();         // claimable expiry
            $table->timestamps();

            $table->foreign('entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['sender_id', 'created_at']);
            $table->index(['recipient_id', 'created_at']);
        });

        // §F2.2
        Schema::create('fx_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->foreignId('from_asset_id')->constrained('assets');
            $table->foreignId('to_asset_id')->constrained('assets');
            $table->decimal('from_amount', 78, 0);
            $table->decimal('to_amount', 78, 0);
            $table->decimal('rate', 38, 18);
            $table->unsignedInteger('spread_bps');
            $table->string('source', 32);
            $table->string('context', 16)->default('swap');      // swap | ramp | card_settle
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('conversions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('quote_id')->constrained('fx_quotes');
            $table->string('context', 16);
            $table->uuid('entry_id')->nullable();
            $table->string('idempotency_key', 160)->unique();
            $table->timestamps();

            $table->foreign('entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        // §F1.2
        Schema::create('user_spending_priority', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');             // 1 = spend first
            $table->foreignId('asset_id')->constrained('assets');
            $table->primary(['user_id', 'asset_id']);
            $table->unique(['user_id', 'position'], 'uq_priority_pos');
        });

        Schema::create('ramp_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('direction', 4);                      // on | off
            $table->string('rail', 24);                          // bank_transfer | mobile_wallet | card_topup
            $table->foreignId('fiat_asset_id')->constrained('assets');
            $table->decimal('fiat_amount', 38, 0);               // paisa
            $table->string('provider_ref', 128)->nullable();
            $table->string('beneficiary', 160)->nullable();      // off-ramp / payout destination
            $table->string('status', 24)->default('pending');
            $table->uuid('entry_id')->nullable();
            $table->timestamps();

            $table->foreign('entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->unique(['rail', 'provider_ref'], 'uq_ramp_provider'); // idempotent vs PSP callback
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramp_orders');
        Schema::dropIfExists('user_spending_priority');
        Schema::dropIfExists('conversions');
        Schema::dropIfExists('fx_quotes');
        Schema::dropIfExists('transfers');
    }
};
