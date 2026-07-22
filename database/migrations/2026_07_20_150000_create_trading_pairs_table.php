<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 5 (Exchange): admin-configurable supported trading pairs. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_pairs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('from_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('to_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedInteger('spread_bps')->nullable();     // override; null = global default
            $table->decimal('min_amount', 78, 0)->default(0);      // from-asset base units
            $table->decimal('max_amount', 78, 0)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['from_asset_id', 'to_asset_id'], 'uq_trading_pair');
            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_pairs');
    }
};
