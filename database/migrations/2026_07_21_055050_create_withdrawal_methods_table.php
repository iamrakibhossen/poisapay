<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiat withdrawal (payout) rails, configurable per currency by operators — the
 * mirror of deposit_methods. Which methods a user sees when cashing out is driven
 * entirely by this table (e.g. BDT → bKash/Nagad/Rocket/bank), so payout options
 * are dynamic by currency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('name', 80);                    // "bKash", "Nagad", "Bank transfer"
            $table->string('type', 16);                    // bank | mobile
            $table->json('details')->nullable();           // rail config (e.g. account-number label/hint)
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
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_methods');
    }
};
