<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 7: merchant profiles (§8) — a User opts into accepting payments. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('business_name', 120);
            $table->string('slug', 140)->unique();
            $table->string('category', 48)->nullable();
            $table->string('website', 160)->nullable();
            $table->string('support_email', 160)->nullable();
            $table->string('statement_descriptor', 22)->nullable(); // shows on payer's history
            $table->foreignId('settlement_asset_id')->nullable()->constrained('assets');
            $table->unsignedInteger('fee_bps')->nullable();          // null => use global default
            $table->string('status', 16)->default('pending');        // pending|active|suspended
            $table->boolean('auto_settle')->default(false);
            $table->text('suspension_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // Record the processing fee taken on each paid invoice (base units, invoice asset).
        Schema::table('merchant_invoices', function (Blueprint $table) {
            $table->decimal('fee_amount', 78, 0)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('merchant_invoices', function (Blueprint $table) {
            $table->dropColumn('fee_amount');
        });
        Schema::dropIfExists('merchants');
    }
};
