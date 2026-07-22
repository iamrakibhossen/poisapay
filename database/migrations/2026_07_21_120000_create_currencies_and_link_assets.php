<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "One coin, many networks" — introduce a logical `currencies` layer that owns
 * coin-level identity (USDT, ETH, BDT …). Each `assets` row stays a per-chain
 * deployment (network) but now points at its currency. The ledger/custody keep
 * keying off asset_id — this is purely additive grouping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('symbol', 16)->unique();          // USDT, ETH, BDT
            $table->string('name', 48);
            $table->string('kind', 8)->default('crypto');    // crypto | fiat
            $table->boolean('is_stablecoin')->default(false);
            $table->unsignedTinyInteger('display_decimals')->nullable();
            $table->string('icon', 32)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('assets', function (Blueprint $table) {
            // Nullable + additive: existing rows are backfilled below, and the
            // ledger never needs this column. Follows the chain_id FK convention.
            $table->foreignId('currency_id')->nullable()->after('id')->constrained('currencies')->nullOnDelete();
        });

        $this->backfill();
    }

    /** Create one currency per distinct symbol and link its network rows. */
    private function backfill(): void
    {
        $bySymbol = DB::table('assets')->get()->groupBy('symbol');

        foreach ($bySymbol as $symbol => $rows) {
            $lead = $rows->first();

            $currencyId = DB::table('currencies')->insertGetId([
                'symbol' => $symbol,
                'name' => $lead->name,
                'kind' => $lead->kind,
                'is_stablecoin' => $rows->contains(fn ($r) => (bool) $r->is_stablecoin),
                'sort' => $lead->sort,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('assets')->where('symbol', $symbol)->update(['currency_id' => $currencyId]);
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });

        Schema::dropIfExists('currencies');
    }
};
