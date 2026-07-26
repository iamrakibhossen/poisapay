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
            // Order bump (checkout add-on) + 1-click upsell (thank-you page).
            $table->foreignUuid('bump_product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->bigInteger('bump_price_amount')->nullable();  // override price (minor units); null = product price
            $table->string('bump_headline', 160)->nullable();
            $table->string('bump_description', 400)->nullable();
            $table->foreignUuid('upsell_product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->bigInteger('upsell_price_amount')->nullable();
            $table->string('upsell_headline', 160)->nullable();
            $table->string('upsell_description', 400)->nullable();
            $table->string('name', 160);
            $table->string('slug', 200);                          // public /p/{slug}
            $table->string('status', 16)->default('draft');      // SalesPageStatus
            $table->jsonb('sections')->default('[]');            // ordered builder blocks (published v2 doc)
            $table->jsonb('draft')->nullable();                  // working v2 document (editor autosave)
            $table->jsonb('theme')->default('{}');
            $table->jsonb('seo')->default('{}');
            $table->jsonb('tracking')->default('{}');            // pixels / CAPI / GTM / UTM cfg
            $table->unsignedInteger('version')->default(1);      // bumped on save → cache-bust
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'status']);
            $table->index('product_id');
            $table->index('bump_product_id');
            $table->index('upsell_product_id');
        });
        // Public lookup: unique slug among live rows; partial index for published only.
        DB::statement('CREATE UNIQUE INDEX shop_sales_pages_slug_unique ON shop_sales_pages (slug) WHERE deleted_at IS NULL');
        DB::statement("CREATE INDEX shop_sales_pages_published ON shop_sales_pages (seller_id) WHERE status = 'published' AND deleted_at IS NULL");

        // ── Builder revisions + saved blocks (block-tree editor v2) ─────────────
        Schema::create('shop_page_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_page_id')->constrained('shop_sales_pages')->cascadeOnDelete();
            $table->foreignUuid('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('label', 80)->nullable();       // e.g. "Published", "Autosave"
            $table->jsonb('document');                     // full v2 document snapshot
            $table->timestamp('created_at')->nullable();

            $table->index(['sales_page_id', 'version']);
            $table->index('author_user_id');
        });

        Schema::create('shop_saved_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('kind', 24)->default('section'); // section | component
            $table->jsonb('node');                          // a serialised BuilderNode subtree
            $table->timestamps();

            $table->index(['seller_id', 'kind']);
        });

        // ── Custom domains (one per sales page) ─────────────────────────────────
        // Ownership is proven via a DNS TXT challenge + routing (CNAME/A) check,
        // then SSL is provisioned. `host` is stored normalized (lowercase, punycode)
        // and is globally unique — a host belongs to exactly one shop/page.
        Schema::create('shop_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->foreignUuid('sales_page_id')->unique()->constrained('shop_sales_pages')->cascadeOnDelete();
            $table->string('host', 253)->unique();               // normalized fqdn
            $table->string('status', 16)->default('pending');    // DomainStatus
            $table->string('ssl_status', 16)->default('pending'); // DomainSslStatus
            $table->string('dns_record_type', 8)->nullable();    // detected routing record (cname)
            $table->string('verification_token', 64);            // TXT ownership challenge
            $table->unsignedSmallInteger('verify_attempts')->default(0);
            $table->unsignedSmallInteger('ssl_attempts')->default(0);
            $table->string('last_error', 255)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('disabled_at')->nullable();        // admin kill-switch
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            // Routing resolves only serviceable rows; keep the status filter cheap.
            $table->index('status');
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
            // --- merged: FK indexes / constraints (was a trailing patch migration) ---
            $table->index('product_id');

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
            // --- merged: FK indexes / constraints (was a trailing patch migration) ---
            $table->index('offer_product_id');
        });

        // Self-reference added after create (PG needs the PK to exist first).
        Schema::table('shop_funnel_steps', function (Blueprint $table) {
            $table->foreign('parent_step_id')->references('id')->on('shop_funnel_steps')->nullOnDelete();
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
