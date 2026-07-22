<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase: deposit methods (§6.1). Not every asset is depositable, and a
 * depositable one may offer several methods (a bank transfer, a mobile wallet,
 * a specific chain address). Fiat/manual deposits reuse the `deposits` table
 * via nullable on-chain columns + a method reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->boolean('deposit_enabled')->default(true)->after('is_active');
        });

        Schema::create('deposit_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('name', 80);                    // "bKash", "City Bank", "USDT — Tron"
            $table->string('type', 16);                    // bank | mobile | crypto | manual
            $table->json('details')->nullable();           // type-specific fields (account no, address, …)
            $table->text('instructions')->nullable();
            $table->decimal('min_amount', 78, 0)->default(0);
            $table->decimal('max_amount', 78, 0)->nullable();
            $table->decimal('fixed_fee', 78, 0)->default(0);
            $table->unsignedInteger('percent_fee_bps')->default(0);
            $table->string('logo', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['asset_id', 'is_active']);
        });

        // Let the deposits table also hold manual (non-on-chain) deposits.
        Schema::table('deposits', function (Blueprint $table) {
            $table->foreignUuid('deposit_address_id')->nullable()->change();
            $table->foreignUuid('onchain_tx_id')->nullable()->change();
            $table->unsignedInteger('required_confirmations')->default(0)->change();
            $table->string('source', 12)->default('onchain')->after('asset_id'); // onchain | manual
            $table->foreignUuid('deposit_method_id')->nullable()->after('deposit_address_id')->constrained('deposit_methods')->nullOnDelete();
            $table->string('reference', 120)->nullable()->after('onchain_tx_id'); // payer's txn reference
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deposit_method_id');
            $table->dropColumn(['source', 'reference']);
        });
        Schema::dropIfExists('deposit_methods');
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('deposit_enabled');
        });
    }
};
