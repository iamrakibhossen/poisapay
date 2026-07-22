<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's saved fiat payout accounts (bank accounts / mobile-wallet numbers).
 * Users can save multiple accounts per currency and reuse them at cash-out time —
 * the address-book equivalent for fiat off-ramps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('withdrawal_method_id')->nullable()->constrained('withdrawal_methods')->nullOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('account_name', 120);
            $table->string('account_number', 64);
            $table->string('bank_name', 120)->nullable();   // for bank-type methods
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'withdrawal_method_id', 'account_number'], 'uq_payout_account');
            $table->index(['user_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
