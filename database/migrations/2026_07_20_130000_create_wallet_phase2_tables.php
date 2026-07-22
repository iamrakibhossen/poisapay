<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 2 (Wallet): address book + favorite assets. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_book_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('label', 64);
            $table->foreignId('chain_id')->nullable()->constrained('chains');
            $table->foreignId('asset_id')->nullable()->constrained('assets');
            $table->string('address', 128);
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_favorite']);
            $table->unique(['user_id', 'address', 'chain_id'], 'uq_addressbook_user_address');
        });

        Schema::create('user_favorite_assets', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['user_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorite_assets');
        Schema::dropIfExists('address_book_entries');
    }
};
