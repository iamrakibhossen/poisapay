<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 3 (Blockchain): RPC endpoints + node health, and per-chain gas wallets. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpc_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('chain_id')->constrained('chains')->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('url', 255);
            $table->unsignedSmallInteger('priority')->default(1);   // lower = tried first
            $table->unsignedSmallInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);

            // Node health (populated by the health checker).
            $table->string('status', 12)->default('unknown');      // up | degraded | down | unknown
            $table->unsignedBigInteger('last_block')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['chain_id', 'is_active', 'priority']);
        });

        Schema::create('gas_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('chain_id')->constrained('chains')->cascadeOnDelete();
            $table->string('address', 64)->nullable();
            $table->decimal('balance', 78, 0)->default(0);         // native base units
            $table->decimal('min_threshold', 78, 0)->default(0);   // top-up alert level
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('chain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_wallets');
        Schema::dropIfExists('rpc_endpoints');
    }
};
