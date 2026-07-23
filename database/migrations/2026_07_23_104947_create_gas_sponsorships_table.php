<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records native-gas top-ups sent to an on-chain address so it can pay for a
 * subsequent operation (e.g. a TRC20 sweep burning TRX for energy). One active row
 * per (chain, target, purpose) gives idempotency; `attempts` + `status=failed` give
 * bounded retry / dead-lettering; `tx_hash` + timestamps give a full audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gas_sponsorships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('chain_id')->constrained('chains');
            $table->string('target_address', 64);
            $table->string('purpose', 24)->default('sweep');
            $table->string('status', 16)->default('pending'); // pending | funded | ready | failed
            $table->decimal('amount_sun', 30, 0)->default(0);  // native base units sent
            $table->string('tx_hash', 80)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error')->nullable();
            $table->timestamp('funded_at')->nullable();
            $table->timestamps();

            $table->unique(['chain_id', 'target_address', 'purpose'], 'uq_gas_sponsorship');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_sponsorships');
    }
};
