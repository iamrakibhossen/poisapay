<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 9: admin-configurable reward campaigns (§F5) — no hardcoded bonuses. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 48)->unique();          // welcome | referral_referrer | referral_referee | cashback | manual | daily
            $table->string('name', 120);
            $table->string('type', 24);                    // fixed | percentage
            $table->foreignId('asset_id')->nullable()->constrained('assets');
            $table->decimal('amount', 78, 0)->nullable();  // fixed payout, base units
            $table->unsignedInteger('rate_bps')->nullable(); // percentage payout (of a driving amount)
            $table->decimal('min_spend', 78, 0)->nullable(); // percentage campaigns: minimum to qualify
            $table->decimal('max_reward', 78, 0)->nullable(); // percentage campaigns: cap per event
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_campaigns');
    }
};
