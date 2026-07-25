<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seller-earnings hold → release. When `sell_earnings_hold` is on, a sale credits
 * the seller's net to their HELD balance (ledger user:locked) instead of spendable.
 * A scheduled command releases it to spendable once the buyer's refund window has
 * passed. These two columns track that lifecycle per order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->boolean('earnings_held')->default(false)->after('seller_net_amount');
            $table->timestamp('earnings_released_at')->nullable()->after('earnings_held');
        });

        // The release scan: held earnings not yet released, past their window.
        DB::statement('CREATE INDEX shop_orders_earnings_release ON shop_orders (refund_window_ends_at) WHERE earnings_held AND earnings_released_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS shop_orders_earnings_release');
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn(['earnings_held', 'earnings_released_at']);
        });
    }
};
