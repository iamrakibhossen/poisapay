<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sell module — digital delivery (downloads, licenses), reviews, and order-centric
 * messaging (threads, messages, attachments). Delivery grants are token-addressed
 * (signed, expiring, count-limited); licenses are pooled and issued atomically.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Download grants ─────────────────────────────────────────────────────
        Schema::create('shop_downloads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_item_id')->constrained('shop_order_items')->cascadeOnDelete();
            $table->foreignUuid('product_file_id')->constrained('shop_product_files');
            $table->foreignUuid('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();                // signed-URL subject
            $table->unsignedInteger('max_downloads')->default(5);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamps();

            $table->index('order_item_id');
        });

        Schema::create('shop_download_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('download_id')->constrained('shop_downloads')->cascadeOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['download_id', 'created_at']);
        });

        // ── License keys (pooled + issued) ──────────────────────────────────────
        Schema::create('shop_licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained('shop_order_items')->nullOnDelete();
            $table->text('key_ciphertext');                       // encrypted at rest
            $table->string('status', 12)->default('available');   // LicenseStatus
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('order_item_id');
        });
        // Atomic "grab next available key for product" — partial index keeps the pool tiny.
        DB::statement("CREATE INDEX shop_licenses_pool ON shop_licenses (product_id) WHERE status = 'available'");

        // ── Reviews (verified buyer only) ───────────────────────────────────────
        Schema::create('shop_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('seller_id')->constrained('shop_sellers')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('shop_orders')->cascadeOnDelete(); // proves purchase
            $table->foreignUuid('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating');                // 1..5
            $table->string('title', 160)->nullable();
            $table->text('body')->nullable();
            $table->jsonb('media')->nullable();
            $table->string('status', 12)->default('published');   // pending|published|hidden
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'product_id']);           // one review per purchase
            $table->index(['product_id', 'status', 'created_at']);
        });

        // ── Order-centric messaging ─────────────────────────────────────────────
        // Order-centric chat: no thread table — one shared conversation per order,
        // messages reference the order directly. Inbox ordering + unread state live
        // on shop_orders (see the orders migration): fewer joins, at the cost of the
        // order row taking the messaging write.
        Schema::create('shop_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->string('author_type', 12);                    // buyer|seller|operator|system
            $table->uuid('author_id')->nullable();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);            // conversation pagination
        });

        Schema::create('shop_message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('shop_messages')->cascadeOnDelete();
            $table->string('disk', 32);
            $table->string('path', 400);
            $table->string('original_name', 200);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime', 100)->nullable();
            $table->timestamps();

            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_message_attachments');
        Schema::dropIfExists('shop_messages');
        Schema::dropIfExists('shop_reviews');
        Schema::dropIfExists('shop_licenses');
        Schema::dropIfExists('shop_download_events');
        Schema::dropIfExists('shop_downloads');
    }
};
