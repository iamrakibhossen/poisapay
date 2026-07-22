<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chain / asset registry + custody xpubs (TDD §7.2, §F1.2).
 * Assets are generalised to hold fiat currencies exactly as crypto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chains', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('key', 16)->unique();        // ethereum | bsc | tron
            $table->string('name', 48);
            $table->string('native_symbol', 12);
            $table->unsignedTinyInteger('min_confirmations')->default(12);
            $table->boolean('is_evm')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('symbol', 16);                          // ETH, USDT, BDT
            $table->string('name', 48);
            $table->string('kind', 8)->default('crypto');          // crypto | fiat (§F1.2)
            $table->char('currency_code', 3)->nullable();          // BDT, USD for fiat
            $table->foreignId('chain_id')->nullable()->constrained('chains')->cascadeOnDelete();
            $table->string('contract_address', 64)->nullable();    // null for native / fiat
            $table->unsignedTinyInteger('decimals');               // 18 native, 6 USDT, 2 fiat
            $table->unsignedTinyInteger('min_confirmations')->nullable();
            $table->string('withdrawal_min', 78)->default('0');    // base units
            $table->string('withdrawal_fee', 78)->default('0');
            $table->boolean('is_stablecoin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            // Partial unique for native assets (one native per chain) per §7.2.
            $table->unique(['chain_id', 'contract_address'], 'uq_asset_chain_contract');
            $table->index(['kind', 'is_active']);
        });

        // One native asset per chain (contract_address IS NULL) — partial unique index.
        DB::statement('CREATE UNIQUE INDEX uq_native_per_chain ON assets (chain_id) WHERE contract_address IS NULL AND chain_id IS NOT NULL');

        Schema::create('custody_xpubs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('chain_id')->constrained('chains')->cascadeOnDelete();
            $table->string('label', 48);
            $table->text('xpub');                                  // PUBLIC only (D4)
            $table->string('derivation_path', 48);
            $table->unsignedBigInteger('next_index')->default(0);  // monotonic counter (§4.2)
            $table->string('purpose', 16)->default('deposit');     // deposit | cold-watch
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Defence in depth: refuse anything that looks like an xpriv (§9.3, CHECK).
        });
        DB::statement("ALTER TABLE custody_xpubs ADD CONSTRAINT ck_never_xpriv CHECK (xpub NOT LIKE 'xprv%' AND xpub NOT LIKE 'tprv%' AND xpub NOT LIKE '%priv%')");
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_xpubs');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('chains');
    }
};
