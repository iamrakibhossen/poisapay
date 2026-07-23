<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wave 2 — per-(chain,address) nonce reservation for EVM withdrawal signing. The
 * NonceManager reconciles this with the on-chain pending count and hands out
 * strictly increasing nonces so multiple withdrawals broadcast in one tick don't
 * collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evm_nonces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('chain', 16);
            $table->string('address', 64);
            $table->unsignedBigInteger('next_nonce')->default(0);
            $table->timestamps();

            $table->unique(['chain', 'address'], 'uq_evm_nonce');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evm_nonces');
    }
};
