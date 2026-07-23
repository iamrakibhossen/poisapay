<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wave 4 (encryption review) — encrypt fiat payout details (bank / mobile-wallet
 * account info) at rest. The column moves json -> text so it can hold the
 * Laravel-encrypted payload; the model's `encrypted:array` cast keeps callers
 * unchanged. Raw ALTER with USING is used so Postgres casts existing rows safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE withdrawals ALTER COLUMN payout_details TYPE text USING payout_details::text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE withdrawals ALTER COLUMN payout_details TYPE json USING payout_details::json');
    }
};
