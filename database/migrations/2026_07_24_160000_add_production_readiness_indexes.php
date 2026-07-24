<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Production-readiness indexing pass (zero-downtime, additive only).
 *
 * Postgres does NOT auto-index foreign keys, and several hot query paths filter
 * on (user_id, created_at) rolling windows (velocity/risk/dashboards) that the
 * existing (user_id, status) indexes do not cover. Indexes are built CONCURRENTLY
 * so writes are never blocked, IF NOT EXISTS so the migration is idempotent, and
 * PARTIAL (WHERE ... IS NOT NULL) on sparse 1:1 ledger-entry references to keep
 * them tiny. No index is dropped. Pgsql-only (the app runs on Postgres in every
 * environment); other drivers no-op.
 */
return new class extends Migration
{
    // CREATE INDEX CONCURRENTLY cannot run inside a transaction.
    public $withinTransaction = false;

    /** @return array<int, array{0:string,1:string}> [indexName, "table (cols) [WHERE ...]"] */
    private function indexes(): array
    {
        return [
            // ── Hot rolling-window / per-user ordering (velocity, risk, dashboards) ──
            ['pp_idx_withdrawals_user_created', 'withdrawals (user_id, created_at DESC)'],
            ['pp_idx_deposits_user_created', 'deposits (user_id, created_at DESC)'],

            // ── Admin "attention"/list counts missing a status index ──
            ['pp_idx_card_authorizations_status', 'card_authorizations (status)'],
            ['pp_idx_webhook_logs_status_resolved', 'webhook_logs (status, resolved)'],

            // ── Unindexed foreign keys on money/hot tables (JOINs + FK integrity) ──
            ['pp_idx_ledger_lines_asset', 'ledger_lines (asset_id)'],
            ['pp_idx_deposits_asset', 'deposits (asset_id)'],
            ['pp_idx_withdrawals_asset', 'withdrawals (asset_id)'],
            ['pp_idx_transfers_asset', 'transfers (asset_id)'],
            ['pp_idx_sweeps_asset', 'sweeps (asset_id)'],
            ['pp_idx_conversions_quote', 'conversions (quote_id)'],
            ['pp_idx_fx_quotes_from_asset', 'fx_quotes (from_asset_id)'],
            ['pp_idx_fx_quotes_to_asset', 'fx_quotes (to_asset_id)'],
            ['pp_idx_treasury_moves_asset', 'treasury_moves (asset_id)'],
            ['pp_idx_treasury_moves_chain', 'treasury_moves (chain_id)'],
            ['pp_idx_card_authorizations_funding_asset', 'card_authorizations (funding_asset_id)'],

            // ── Compliance / admin FK lookups (partial — sparsely populated) ──
            ['pp_idx_aml_alerts_case', 'aml_alerts (case_id) WHERE case_id IS NOT NULL'],
            ['pp_idx_compliance_cases_assigned', 'compliance_cases (assigned_to) WHERE assigned_to IS NOT NULL'],
            ['pp_idx_kyc_profiles_reviewed_by', 'kyc_profiles (reviewed_by) WHERE reviewed_by IS NOT NULL'],
        ];
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->indexes() as [$name, $def]) {
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} ON {$def}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->indexes() as [$name]) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
        }
    }
};
