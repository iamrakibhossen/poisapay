<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Harden the double-entry balance check to be PER ASSET.
 *
 * The original trigger summed (debit − credit) across every line of an entry
 * regardless of asset_id, so a genuinely cross-currency-unbalanced entry could
 * commit as long as the raw base-unit totals happened to cancel (e.g. 100000
 * paisa BDT against 100000 µUSDT). A crypto ledger must balance WITHIN each
 * asset/currency and never across them. This replaces the function so every
 * asset in the entry must net to zero on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_entry_balanced() RETURNS trigger AS $$
            DECLARE
                bad RECORD;
            BEGIN
                SELECT asset_id,
                       SUM(CASE WHEN side = 'debit' THEN amount ELSE -amount END) AS imbalance
                  INTO bad
                  FROM ledger_lines
                 WHERE entry_id = NEW.entry_id
                 GROUP BY asset_id
                HAVING SUM(CASE WHEN side = 'debit' THEN amount ELSE -amount END) <> 0
                 LIMIT 1;

                IF FOUND THEN
                    RAISE EXCEPTION 'Unbalanced journal entry % on asset %: debit-credit imbalance = %',
                        NEW.entry_id, bad.asset_id, bad.imbalance;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        // Restore the original asset-agnostic sum.
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
        SQL);
    }
};
