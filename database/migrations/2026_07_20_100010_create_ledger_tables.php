<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry, append-only ledger (TDD §5, D1/D2/D3).
 * Money as NUMERIC(38,0) minor units. A deferred trigger enforces
 * Σ debits = Σ credits per entry — balance is guaranteed by the DB itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A ledger account is a balance bucket: a user wallet OR a system account.
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 40);                     // LedgerAccountType (user:available, treasury:hot, ...)
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('normal_side', 6);               // debit | credit
            $table->string('label', 64)->nullable();
            $table->timestamps();

            // A user has at most one account of a given type per asset; system accounts are user-null.
            $table->unique(['type', 'user_id', 'asset_id'], 'uq_account_identity');
            $table->index(['user_id', 'asset_id']);
        });

        // Materialised balance, locked FOR UPDATE on every post (§7.2).
        Schema::create('account_balances', function (Blueprint $table) {
            $table->foreignUuid('account_id')->primary()->constrained('ledger_accounts')->cascadeOnDelete();
            $table->decimal('balance', 38, 0)->default(0);  // signed minor units in the account's normal orientation
            $table->unsignedBigInteger('version')->default(0); // optimistic-lock companion
            $table->timestamp('updated_at')->nullable();
        });

        // A balanced value event (§5.1). Idempotency key collapses retries to no-ops (D5).
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 40);                     // deposit.credit, withdrawal.settle, transfer, lock, ...
            $table->string('status', 16)->default('completed');
            $table->string('idempotency_key', 160)->unique();
            $table->uuid('reverses_entry_id')->nullable();  // correction linkage (§5.3)
            $table->string('memo', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
        });

        // Self-referential correction linkage — added after the PK exists.
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('reverses_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        // Debit/credit lines. Never edited; corrections are reversing entries.
        Schema::create('ledger_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('ledger_accounts');
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('side', 6);                      // debit | credit
            $table->decimal('amount', 38, 0);               // positive minor units
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'created_at']);    // hot path (§7.4)
            $table->index('entry_id');
        });
        DB::statement('ALTER TABLE ledger_lines ADD CONSTRAINT ck_line_amount_positive CHECK (amount > 0)');

        // Deferred balance trigger: at COMMIT, every entry must have Σdebit = Σcredit.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_entry_balanced() RETURNS trigger AS $$
            DECLARE
                imbalance NUMERIC(38,0);
            BEGIN
                SELECT COALESCE(SUM(CASE WHEN side = 'debit' THEN amount ELSE -amount END), 0)
                  INTO imbalance
                  FROM ledger_lines
                 WHERE entry_id = NEW.entry_id;

                IF imbalance <> 0 THEN
                    RAISE EXCEPTION 'Unbalanced journal entry %: debit-credit imbalance = %', NEW.entry_id, imbalance;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;

            CREATE CONSTRAINT TRIGGER trg_entry_balanced
                AFTER INSERT ON ledger_lines
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION assert_entry_balanced();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_entry_balanced ON ledger_lines; DROP FUNCTION IF EXISTS assert_entry_balanced();');
        Schema::dropIfExists('ledger_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('account_balances');
        Schema::dropIfExists('ledger_accounts');
    }
};
