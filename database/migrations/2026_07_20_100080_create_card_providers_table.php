<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Card issuer/BIN providers (TDD §F3). PoisaPay integrates licensed issuers;
 * this registry lets operators add more providers, and the demo card generator
 * provisions cards through the selected one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();              // program code
            $table->string('network', 12)->default('visa'); // visa | mastercard
            $table->string('bin', 8)->nullable();          // BIN prefix for realism
            $table->boolean('supports_virtual')->default(true);
            $table->boolean('supports_physical')->default(false);
            $table->char('settlement_currency', 3)->default('USD');
            $table->string('api_base', 160)->nullable();
            $table->boolean('is_demo')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->foreignUuid('card_provider_id')->nullable()->after('user_id')
                ->constrained('card_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_provider_id');
        });
        Schema::dropIfExists('card_providers');
    }
};
