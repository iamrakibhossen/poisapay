<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Card system (TDD §F3). PoisaPay holds only issuer tokens — never the PAN. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('program', 24);
            $table->string('type', 8);                    // virtual | physical
            $table->string('network', 12);               // visa | mastercard
            $table->string('issuer_card_ref', 128);      // partner token; NEVER the PAN
            $table->char('last4', 4)->nullable();
            $table->string('status', 16)->default('inactive');
            $table->decimal('daily_limit', 38, 0)->nullable();  // settlement-currency minor units
            $table->decimal('per_tx_limit', 38, 0)->nullable();
            $table->char('settlement_currency', 3)->default('USD');
            $table->foreignUuid('frozen_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique('issuer_card_ref', 'uq_issuer_card');
            $table->index(['user_id', 'status']);
        });
        // Defence in depth: refuse anything that looks like a raw PAN (§F3.5).
        DB::statement(<<<'SQL'
            ALTER TABLE cards ADD CONSTRAINT ck_no_pan CHECK (
                length(issuer_card_ref) > 19
                OR (issuer_card_ref NOT LIKE '4%' AND issuer_card_ref NOT LIKE '5%')
            )
        SQL);

        Schema::create('card_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('card_id')->constrained('cards')->cascadeOnDelete();
            $table->string('network_auth_id', 64);        // network idempotency key
            $table->decimal('amount', 38, 0);             // settlement-currency minor units
            $table->char('currency_code', 3);
            $table->char('mcc', 4)->nullable();
            $table->string('merchant', 128)->nullable();
            $table->foreignId('funding_asset_id')->nullable()->constrained('assets');
            $table->decimal('held_amount', 78, 0)->nullable();  // crypto base units locked
            $table->foreignUuid('quote_id')->nullable()->constrained('fx_quotes');
            $table->string('status', 16)->default('approved');
            $table->uuid('hold_entry_id')->nullable();
            $table->uuid('settle_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('hold_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('settle_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->unique('network_auth_id', 'uq_network_auth'); // re-sent auths never double-hold
            $table->index(['card_id', 'created_at']);
        });

        Schema::create('card_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('authorization_id')->constrained('card_authorizations');
            $table->string('reason', 48);
            $table->string('status', 16)->default('open'); // open | represented | won | lost
            $table->decimal('amount', 38, 0);
            $table->uuid('entry_id')->nullable();
            $table->timestamps();

            $table->foreign('entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_disputes');
        Schema::dropIfExists('card_authorizations');
        Schema::dropIfExists('cards');
    }
};
