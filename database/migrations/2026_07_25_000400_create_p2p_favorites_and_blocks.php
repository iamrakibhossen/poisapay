<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user P2P relationships: favourite merchants (a shortcut list) and blocks
 * (either direction prevents an order opening between the two parties, and hides
 * each other's ads in the marketplace).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('merchant_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'merchant_id'], 'uq_p2p_fav');
            $table->index('merchant_id');
        });

        Schema::create('p2p_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'blocked_id'], 'uq_p2p_block');
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_blocks');
        Schema::dropIfExists('p2p_favorites');
    }
};
