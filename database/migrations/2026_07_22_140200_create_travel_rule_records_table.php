<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Wave 5 — Travel Rule (FATF R.16) originator/beneficiary records for VASP transfers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_rule_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('withdrawal_id')->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('assets');
            $table->string('direction', 4);        // in | out
            $table->decimal('amount', 78, 0);
            $table->string('originator_name', 191)->nullable();
            $table->string('originator_account', 191)->nullable();
            $table->string('beneficiary_name', 191)->nullable();
            $table->string('beneficiary_vasp', 191)->nullable();
            $table->string('beneficiary_address', 191)->nullable();
            $table->string('status', 16)->default('pending'); // pending | submitted | not_required | failed
            $table->string('provider', 32)->nullable();
            $table->string('provider_ref', 128)->nullable();
            $table->timestamps();

            $table->foreign('withdrawal_id')->references('id')->on('withdrawals')->nullOnDelete();
            $table->index('status');
            $table->index('withdrawal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_rule_records');
    }
};
