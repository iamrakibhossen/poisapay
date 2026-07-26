<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sell module — commerce core: customers, coupons, orders, order items, the
 * append-only order event log, and shipments. Designed for 200M orders / 500M
 * items: narrow rows, composite indexes on the real access patterns, idempotency
 * key to make duplicate orders impossible, no soft-deletes on hot tables.
 * Money is a record of what the Ledger moved (ledger_entry_id), never re-derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Buyers are core Users (a PoisaPay wallet is required to pay), so there is
        // no separate customer entity. A seller's "customers" list is derived by
        // aggregating orders on (seller_id, buyer_user_id) — see the orders index.

        // ── Coupons ─────────────────────────────────────────────────────────────
        Schema::create('shop_coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('shop_products')->cascadeOnDelete(); // null = seller-wide
            $table->string('code', 40);
            $table->string('type', 10);                           // CouponType: percent|fixed
            $table->integer('value');                             // bps (percent) or minor units (fixed)
            $table->bigInteger('min_order_amount')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['seller_id', 'code']);
            $table->index(['seller_id', 'is_active']);
            // --- merged: FK indexes / constraints (was a trailing patch migration) ---
            $table->index('product_id');

        });

        // ── Orders ──────────────────────────────────────────────────────────────
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();               // human ref PH-XXXXX
            $table->foreignUuid('seller_id')->constrained('shop_sellers');
            $table->foreignUuid('buyer_user_id')->constrained('users');   // buyers are core users
            $table->foreignUuid('sales_page_id')->nullable()->constrained('shop_sales_pages')->nullOnDelete();
            $table->uuid('parent_order_id')->nullable(); // 1-click upsell → the order it followed (self-FK added after create)
            $table->foreignUuid('funnel_id')->nullable()->constrained('shop_funnels')->nullOnDelete();
            $table->foreignUuid('coupon_id')->nullable()->constrained('shop_coupons')->nullOnDelete();
            $table->string('status', 24)->default('pending');     // OrderStatus (state machine)
            $table->bigInteger('subtotal_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('shipping_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->bigInteger('commission_amount')->default(0);
            $table->bigInteger('seller_net_amount')->default(0);
            $table->boolean('earnings_held')->default(false);     // held during the refund window
            $table->timestamp('earnings_released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->bigInteger('refunded_amount')->nullable();    // minor units returned to buyer
            $table->foreignId('asset_id')->constrained('assets');
            $table->string('payment_method', 24)->default('poisapay');
            $table->uuid('ledger_entry_id')->nullable();          // FK-by-reference to core Ledger (source of truth)
            $table->timestamp('refund_window_ends_at')->nullable();
            $table->jsonb('shipping_address')->nullable();
            $table->jsonb('utm')->nullable();
            $table->string('idempotency_key', 190)->unique();     // no duplicate orders, ever
            $table->timestamp('paid_at')->nullable();
            // Order-centric chat lives here (no thread table): inbox ordering + unread.
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('seller_unread')->default(false);
            $table->boolean('buyer_unread')->default(false);
            $table->timestamps();

            // Seller order list: equality(seller,status) → sort(created_at desc).
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index(['buyer_user_id', 'created_at']);       // buyer's "My Purchases"
            $table->index(['seller_id', 'buyer_user_id', 'created_at']); // per-seller customer history + derived customers list
            $table->index(['seller_id', 'created_at']);           // unfiltered list + revenue windows
            // --- merged: FK indexes / constraints (was a trailing patch migration) ---
            $table->index('asset_id');
            $table->index('coupon_id');
            $table->index('funnel_id');
            $table->index('sales_page_id');
            $table->index('parent_order_id');
            $table->index('ledger_entry_id');
            $table->foreign('ledger_entry_id')->references('id')->on('journal_entries')->nullOnDelete();

        });
        // Self-referencing FK — added after the table (+ its PK) exists.
        Schema::table('shop_orders', fn (Blueprint $table) => $table->foreign('parent_order_id')->references('id')->on('shop_orders')->nullOnDelete());
        // Vesting sweep reads only unvested paid orders — keep that index tiny (partial).
        DB::statement("CREATE INDEX shop_orders_vesting ON shop_orders (refund_window_ends_at) WHERE status = 'paid' AND refund_window_ends_at IS NOT NULL");
        // Earnings release scan: held earnings past their window, not yet released.
        DB::statement('CREATE INDEX shop_orders_earnings_release ON shop_orders (refund_window_ends_at) WHERE earnings_held AND earnings_released_at IS NULL');
        // Seller inbox: only orders that have a conversation (partial keeps it tiny).
        DB::statement('CREATE INDEX shop_orders_inbox ON shop_orders (seller_id, last_message_at DESC) WHERE last_message_at IS NOT NULL');

        // ── Order items (main + bumps + accepted upsells) ───────────────────────
        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('shop_products');
            $table->foreignUuid('variant_id')->nullable()->constrained('shop_product_variants');
            $table->string('kind', 12)->default('main');          // OrderItemKind
            $table->string('name_snapshot', 190);                 // denormalized: product name at purchase
            $table->bigInteger('unit_amount');
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('line_total_amount');
            $table->bigInteger('commission_amount')->default(0);
            $table->bigInteger('seller_net_amount')->default(0);
            $table->string('fulfilment_status', 16)->default('pending');
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['product_id', 'created_at']);          // per-product sales analytics feed
            // --- merged: FK indexes / constraints (was a trailing patch migration) ---
            $table->index('variant_id');

        });

        // ── Order events (append-only lifecycle log) ────────────────────────────
        Schema::create('shop_order_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('actor_type', 12)->default('system');  // buyer|seller|operator|system
            $table->uuid('actor_id')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        // ── Shipments (physical fulfilment) ─────────────────────────────────────
        Schema::create('shop_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->string('carrier', 40)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('status', 16)->default('pending');     // pending|shipped|delivered
            $table->jsonb('address')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_shipments');
        Schema::dropIfExists('shop_order_events');
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
        Schema::dropIfExists('shop_coupons');
    }
};
