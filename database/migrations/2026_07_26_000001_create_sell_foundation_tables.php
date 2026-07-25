<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sell module — foundation: sellers + product catalog (products, variants, files,
 * media). See docs/SELL_BACKEND_ARCHITECTURE.md. Postgres 16; UUID PKs (ordered);
 * money = integer minor units + asset_id (the Ledger owns balances). All tables
 * `sell_`-prefixed. Indexes designed for the documented access patterns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Sellers ────────────────────────────────────────────────────────────
        Schema::create('sell_sellers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // Public store name; falls back to the core User's name when null.
            $table->string('brand_name', 120)->nullable();
            $table->text('bio')->nullable();
            $table->string('website', 190)->nullable();
            $table->char('country', 2)->nullable();
            $table->jsonb('categories')->default('[]');
            $table->string('status', 20)->default('draft');       // SellerStatus
            $table->unsignedInteger('commission_bps')->nullable(); // null => platform default
            $table->foreignId('settlement_asset_id')->nullable()->constrained('assets');
            $table->string('kyc_reference', 64)->nullable();
            $table->string('plan', 24)->default('free');              // free|pro|business
            $table->uuid('reviewed_by')->nullable();              // admin id (separate guard, no FK)
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });

        Schema::create('sell_seller_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('sell_sellers')->cascadeOnDelete();
            $table->jsonb('snapshot');
            $table->string('status', 16)->default('pending');     // pending|approved|rejected
            $table->timestamp('submitted_at');
            $table->uuid('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'submitted_at']);
        });

        // ── Products ───────────────────────────────────────────────────────────
        Schema::create('sell_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('sell_sellers')->cascadeOnDelete();
            $table->string('type', 24)->default('digital');       // ProductType (extensible)
            $table->string('name', 190);
            $table->string('slug', 200);
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 16)->default('draft');       // ProductStatus
            $table->bigInteger('price_amount')->default(0);        // minor units
            $table->foreignId('price_asset_id')->constrained('assets');
            $table->bigInteger('compare_price_amount')->nullable();
            $table->boolean('has_variants')->default(false);
            $table->boolean('requires_shipping')->default(false);
            $table->jsonb('attributes')->default('{}');           // type-specific config (extensible)
            $table->jsonb('meta')->default('{}');                 // seo overrides at product level
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('rating_bps')->default(0);    // avg rating ×10000
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Tenant catalog list: equality(seller,status) → sort(created_at).
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index(['type', 'status']);
        });
        // Slug unique per seller (only among live rows); FTS search vector + GIN.
        DB::statement('CREATE UNIQUE INDEX sell_products_seller_slug_unique ON sell_products (seller_id, slug) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE sell_products ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(name,'') || ' ' || coalesce(summary,''))) STORED");
        DB::statement('CREATE INDEX sell_products_search_gin ON sell_products USING gin (search_vector)');

        Schema::create('sell_product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('sell_products')->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            $table->jsonb('options');                             // {Size:"M", Color:"Black"}
            $table->bigInteger('price_amount')->nullable();       // null => inherit product price
            $table->integer('stock')->nullable();                 // null => unlimited (digital)
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'position']);
            $table->unique(['product_id', 'sku']);
        });

        Schema::create('sell_product_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('sell_products')->cascadeOnDelete();
            $table->string('version', 32)->nullable();
            $table->text('changelog')->nullable();
            $table->string('disk', 32);                           // private disk (s3/r2/minio)
            $table->string('path', 400);
            $table->string('original_name', 200);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('scan_status', 16)->default('pending'); // pending|clean|infected
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_current']);
        });

        Schema::create('sell_product_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('sell_products')->cascadeOnDelete();
            $table->string('kind', 12)->default('image');         // image|video
            $table->string('url', 400);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_product_media');
        Schema::dropIfExists('sell_product_files');
        Schema::dropIfExists('sell_product_variants');
        Schema::dropIfExists('sell_products');
        Schema::dropIfExists('sell_seller_applications');
        Schema::dropIfExists('sell_sellers');
    }
};