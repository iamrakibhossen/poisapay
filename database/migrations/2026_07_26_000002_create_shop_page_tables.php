<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sell module — sales pages, custom domains (one per page), and funnel graphs.
 * Flexible builder config (sections/theme/seo/tracking) lives in jsonb so the hot
 * row stays small and cache-friendly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Sales pages (a product may have many) ───────────────────────────────
        Schema::create('shop_sales_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 200);                          // public /p/{slug}
            $table->string('status', 16)->default('draft');      // SalesPageStatus
            $table->jsonb('sections')->default('[]');            // ordered builder blocks
            $table->jsonb('theme')->default('{}');
            $table->jsonb('seo')->default('{}');
            $table->jsonb('tracking')->default('{}');            // pixels / CAPI / GTM / UTM cfg
            $table->unsignedInteger('version')->default(1);      // bumped on save → cache-bust
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'status']);
            $table->index('product_id');
        });
        // Public lookup: unique slug among live rows; partial index for published only.
        DB::statement('CREATE UNIQUE INDEX shop_sales_pages_slug_unique ON shop_sales_pages (slug) WHERE deleted_at IS NULL');
        DB::statement("CREATE INDEX shop_sales_pages_published ON shop_sales_pages (seller_id) WHERE status = 'published' AND deleted_at IS NULL");

        // ── Custom domains (one per sales page) ─────────────────────────────────
        Schema::create('shop_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->foreignUuid('sales_page_id')->unique()->constrained('shop_sales_pages')->cascadeOnDelete();
            $table->string('host', 253)->unique();               // fqdn
            $table->string('status', 16)->default('pending');    // DomainStatus
            $table->string('verification_token', 64)->nullable();
            $table->boolean('ssl_active')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
        });

        // ── Funnels (graph of post-purchase steps) ──────────────────────────────
        Schema::create('shop_funnels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('shop_products')->cascadeOnDelete(); // front product
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'is_active']);
        });

        Schema::create('shop_funnel_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('funnel_id')->constrained('shop_funnels')->cascadeOnDelete();
            $table->foreignUuid('offer_product_id')->constrained('shop_products');
            $table->string('kind', 16);                          // FunnelStepType
            $table->unsignedSmallInteger('position')->default(0);
            $table->uuid('parent_step_id')->nullable();          // downsell hangs off an upsell (graph, no fixed depth)
            $table->bigInteger('price_override_amount')->nullable();
            $table->jsonb('config')->default('{}');
            $table->timestamps();

            $table->index(['funnel_id', 'position']);
            $table->index('parent_step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_funnel_steps');
        Schema::dropIfExists('shop_funnels');
        Schema::dropIfExists('shop_domains');
        Schema::dropIfExists('shop_sales_pages');
    }
};
