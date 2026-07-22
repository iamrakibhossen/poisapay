<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Provider-agnostic card layer: driver column, cardholder registry, API log, card metadata. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_providers', function (Blueprint $table) {
            $table->string('driver', 24)->default('mock')->after('slug');
        });
        DB::table('card_providers')->update(['driver' => 'mock']);

        Schema::table('cards', function (Blueprint $table) {
            $table->string('cardholder_ref', 128)->nullable()->after('issuer_card_ref');
            $table->unsignedTinyInteger('exp_month')->nullable()->after('last4');
            $table->unsignedSmallInteger('exp_year')->nullable()->after('exp_month');
        });

        // One cardholder token per user per provider program.
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('card_provider_id')->constrained('card_providers')->cascadeOnDelete();
            $table->string('driver', 24);
            $table->string('provider_ref', 128);          // cardholder token
            $table->string('status', 24)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'card_provider_id'], 'uq_provider_account_user_program');
            $table->unique(['driver', 'provider_ref'], 'uq_provider_account_token');
        });

        // Every provider API call (both directions) — secrets redacted before store.
        Schema::create('card_provider_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('card_provider_id')->nullable()->constrained('card_providers')->nullOnDelete();
            $table->string('driver', 24);
            $table->foreignUuid('card_id')->nullable()->constrained('cards')->nullOnDelete();
            $table->string('direction', 8);                // outbound | inbound
            $table->string('operation', 64);               // createVirtualCard, webhook, jit, …
            $table->string('method', 8)->nullable();
            $table->string('endpoint', 200)->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->string('error', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['driver', 'created_at']);
            $table->index('card_id');
            $table->index('operation');
        });

        // Generic per-card provider key/values (avoids provider-specific columns).
        Schema::create('card_metadata', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('card_id')->constrained('cards')->cascadeOnDelete();
            $table->string('key', 64);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['card_id', 'key'], 'uq_card_metadata_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_metadata');
        Schema::dropIfExists('card_provider_logs');
        Schema::dropIfExists('provider_accounts');

        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['cardholder_ref', 'exp_month', 'exp_year']);
        });
        Schema::table('card_providers', function (Blueprint $table) {
            $table->dropColumn('driver');
        });
    }
};
