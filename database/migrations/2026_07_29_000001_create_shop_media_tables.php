<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop Media Library — a single, merchant-scoped image table for the Landing Page
 * Builder. Intentionally one table: no folders, no usage pivots. The storage disk
 * is resolved from config (never persisted), so it is not a column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();

            $table->string('path', 500);              // original stored path (relative to disk)
            $table->string('name', 200);              // display name (editable via rename)
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->string('extension', 12);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt', 300)->nullable();

            // { thumb: {path,width,height,mime}, thumb_webp: {...}, medium: {...}, … }.
            $table->jsonb('variants')->default('{}');

            $table->string('status', 16)->default('ready'); // processing | ready | failed
            $table->string('checksum', 64)->nullable();      // sha256 for per-seller dedup
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'created_at']);
            $table->index('checksum');
            $table->index('last_used_at');
        });

        // Dedup: the same bytes uploaded twice by one seller resolve to one row.
        // Runs AFTER create() — a DB::statement() inside the closure executes before
        // the table actually exists (the closure only builds the blueprint).
        DB::statement('CREATE UNIQUE INDEX shop_media_seller_checksum_unique ON shop_media (seller_id, checksum) WHERE checksum IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_media');
    }
};
