<?php

use App\Enums\KycStatus;
use App\Enums\KycTier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // UUID primary keys everywhere per TDD §7 (non-enumerable, custodial-grade).
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('handle')->nullable()->unique(); // P2P send target (§F4.1)
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');

            // Security (§9.2)
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Compliance (§10.1)
            $table->string('kyc_tier')->default(KycTier::Unverified->value);
            $table->string('kyc_status')->default(KycStatus::None->value);

            // Referral (§F5)
            $table->string('referral_code')->nullable()->unique();
            $table->uuid('referred_by')->nullable();

            // Preferences
            $table->string('base_currency', 3)->default('BDT');
            $table->string('locale', 8)->default('en');
            $table->string('timezone', 32)->default('Asia/Dhaka');
            $table->boolean('is_frozen')->default(false);

            $table->rememberToken();
            $table->timestamps();

            $table->index('kyc_tier');
            $table->index('kyc_status');
        });

        // Self-referential FK added after the table (and its PK) exists.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('referred_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
