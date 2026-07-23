<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * P2P USDT marketplace (Binance-style). Crypto is escrowed on the internal
 * ledger via user:p2p_escrow; fiat settles peer-to-peer off-platform. Money is
 * stored as integer base units — decimal(78,0) for crypto, decimal(38,x) for
 * indicative fiat/price (display only). All value moves through the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Payment-method catalog + per-user configured accounts ──
        Schema::create('p2p_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 32);
            $table->string('name', 64);
            $table->string('type', 16);                 // mobile | bank | wallet
            $table->char('country', 2)->nullable();
            $table->string('icon', 64)->nullable();
            $table->json('fields')->nullable();         // account-field schema
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique('key', 'uq_p2p_pm_key');
        });

        Schema::create('p2p_user_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('payment_method_id')->constrained('p2p_payment_methods');
            $table->string('label', 64)->nullable();
            $table->text('account');                    // encrypted ciphertext (model layer)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // ── Merchant reputation profile (P2P-specific stats) ──
        Schema::create('p2p_merchant_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->boolean('vacation_mode')->default(false);
            $table->unsignedTinyInteger('level')->default(0);
            $table->json('badges')->nullable();
            $table->unsignedInteger('trade_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('completion_rate_bps')->default(0);
            $table->unsignedInteger('avg_release_seconds')->nullable();
            $table->unsignedInteger('avg_pay_seconds')->nullable();
            $table->decimal('total_volume', 78, 0)->default(0);   // crypto base units
            $table->decimal('rating', 3, 2)->default(0);
            $table->timestamps();

            $table->unique('user_id', 'uq_p2p_merchant_user');
        });

        // ── Ads ──
        Schema::create('p2p_ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('side', 8);                        // buy | sell
            $table->foreignId('asset_id')->constrained('assets');
            $table->char('fiat_currency', 3)->default('BDT');
            $table->string('price_type', 10);                 // fixed | floating
            $table->decimal('fixed_price', 38, 4)->nullable(); // fiat per 1 crypto
            $table->integer('margin_bps')->nullable();         // signed, for floating
            $table->decimal('min_order', 38, 2);               // fiat
            $table->decimal('max_order', 38, 2);               // fiat
            $table->decimal('available_amount', 78, 0);        // crypto base units left
            $table->decimal('total_amount', 78, 0);            // crypto base units original
            $table->decimal('daily_limit', 78, 0)->nullable(); // crypto per day
            $table->unsignedSmallInteger('payment_window_min')->default(15);
            $table->unsignedInteger('min_completion_bps')->nullable(); // taker gating
            $table->text('auto_reply')->nullable();
            $table->text('terms')->nullable();
            $table->json('countries')->nullable();
            $table->json('trade_hours')->nullable();
            $table->string('status', 12)->default('active');
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['side', 'status', 'asset_id'], 'ix_p2p_ads_book');
            $table->index(['user_id', 'status']);
        });

        Schema::create('p2p_ad_payment_methods', function (Blueprint $table) {
            $table->foreignUuid('ad_id')->constrained('p2p_ads')->cascadeOnDelete();
            $table->foreignUuid('payment_method_id')->constrained('p2p_payment_methods');

            $table->primary(['ad_id', 'payment_method_id']);
        });

        // ── Orders ──
        Schema::create('p2p_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ref', 20);
            $table->foreignUuid('ad_id')->constrained('p2p_ads');
            $table->foreignUuid('buyer_id')->constrained('users');
            $table->foreignUuid('seller_id')->constrained('users');
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('crypto_amount', 78, 0);      // gross USDT escrowed
            $table->decimal('fee_amount', 78, 0)->default(0);  // taker fee (crypto)
            $table->decimal('net_amount', 78, 0);          // crypto to buyer
            $table->unsignedInteger('taker_fee_bps')->default(0);
            $table->decimal('fiat_amount', 38, 2);
            $table->decimal('price', 38, 4);
            $table->char('fiat_currency', 3)->default('BDT');
            $table->foreignUuid('payment_method_id')->nullable()->constrained('p2p_payment_methods');
            $table->string('status', 20)->default('waiting_payment');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('buyer_paid_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('ref', 'uq_p2p_order_ref');
            $table->index(['status', 'expires_at'], 'ix_p2p_orders_expiry');
            $table->index('buyer_id');
            $table->index('seller_id');
            $table->index('ad_id');
        });

        // ── Escrow custody record (links lock/release journal entries) ──
        Schema::create('p2p_escrows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('p2p_orders')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');  // seller
            $table->foreignId('asset_id')->constrained('assets');
            $table->decimal('amount', 78, 0);              // gross locked
            $table->string('status', 10)->default('locked'); // locked | released | refunded
            $table->uuid('lock_entry_id')->nullable();
            $table->uuid('release_entry_id')->nullable();
            $table->timestamps();

            $table->foreign('lock_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('release_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->unique('order_id', 'uq_p2p_escrow_order');
        });

        // ── Chat + timeline ──
        Schema::create('p2p_order_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('p2p_orders')->cascadeOnDelete();
            $table->string('sender_type', 8);             // user | admin | system
            $table->uuid('sender_id')->nullable();
            $table->string('type', 10)->default('text');  // text | image | receipt | system
            $table->text('body')->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at'], 'ix_p2p_msg_order');
        });

        Schema::create('p2p_order_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('p2p_orders')->cascadeOnDelete();
            $table->string('actor_type', 8)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at'], 'ix_p2p_evt_order');
        });

        // ── Disputes ──
        Schema::create('p2p_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('p2p_orders')->cascadeOnDelete();
            $table->foreignUuid('opened_by')->constrained('users');
            $table->string('opened_by_role', 8)->default('user');
            $table->string('reason', 64);
            $table->text('detail')->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignUuid('assigned_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('resolution', 255)->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique('order_id', 'uq_p2p_dispute_order');
        });

        Schema::create('p2p_dispute_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispute_id')->constrained('p2p_disputes')->cascadeOnDelete();
            $table->uuid('uploaded_by');
            $table->string('uploader_role', 8)->default('user');
            $table->string('path', 255);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        $this->seedPaymentMethodCatalog();
    }

    /** Reference data — the fiat rails PoisaPay supports out of the box. */
    private function seedPaymentMethodCatalog(): void
    {
        $now = now();
        $rows = [
            ['bkash', 'bKash', 'mobile', 'BD', 10],
            ['nagad', 'Nagad', 'mobile', 'BD', 20],
            ['rocket', 'Rocket', 'mobile', 'BD', 30],
            ['upay', 'Upay', 'mobile', 'BD', 40],
            ['bank', 'Bank transfer', 'bank', null, 50],
            ['wise', 'Wise', 'wallet', null, 60],
            ['revolut', 'Revolut', 'wallet', null, 70],
            ['payoneer', 'Payoneer', 'wallet', null, 80],
        ];

        DB::table('p2p_payment_methods')->insert(array_map(fn ($r) => [
            'id' => (string) Str::uuid(),
            'key' => $r[0],
            'name' => $r[1],
            'type' => $r[2],
            'country' => $r[3],
            'is_active' => true,
            'sort' => $r[4],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_dispute_evidence');
        Schema::dropIfExists('p2p_disputes');
        Schema::dropIfExists('p2p_order_events');
        Schema::dropIfExists('p2p_order_messages');
        Schema::dropIfExists('p2p_escrows');
        Schema::dropIfExists('p2p_orders');
        Schema::dropIfExists('p2p_ad_payment_methods');
        Schema::dropIfExists('p2p_ads');
        Schema::dropIfExists('p2p_merchant_profiles');
        Schema::dropIfExists('p2p_user_payment_methods');
        Schema::dropIfExists('p2p_payment_methods');
    }
};
