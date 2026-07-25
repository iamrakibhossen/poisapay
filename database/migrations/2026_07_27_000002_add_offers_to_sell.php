<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order-bump (pre-checkout, same order) and 1-click upsell (post-purchase, new
 * order) configuration lives on the sales page — server-authoritative so the
 * offered product + price can never be tampered with from the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sell_sales_pages', function (Blueprint $table) {
            // Order bump — an add-on shown at checkout.
            $table->foreignUuid('bump_product_id')->nullable()->after('product_id')
                ->constrained('sell_products')->nullOnDelete();
            $table->bigInteger('bump_price_amount')->nullable()->after('bump_product_id'); // override price (minor units); null = product price
            $table->string('bump_headline', 160)->nullable()->after('bump_price_amount');
            $table->string('bump_description', 400)->nullable()->after('bump_headline');

            // 1-click upsell — offered on the thank-you page.
            $table->foreignUuid('upsell_product_id')->nullable()->after('bump_description')
                ->constrained('sell_products')->nullOnDelete();
            $table->bigInteger('upsell_price_amount')->nullable()->after('upsell_product_id');
            $table->string('upsell_headline', 160)->nullable()->after('upsell_price_amount');
            $table->string('upsell_description', 400)->nullable()->after('upsell_headline');
        });

        Schema::table('sell_orders', function (Blueprint $table) {
            // Links a 1-click upsell order back to the order it followed.
            $table->foreignUuid('parent_order_id')->nullable()->after('sales_page_id')
                ->constrained('sell_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sell_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_order_id');
        });
        Schema::table('sell_sales_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bump_product_id');
            $table->dropConstrainedForeignId('upsell_product_id');
            $table->dropColumn(['bump_price_amount', 'bump_headline', 'bump_description', 'upsell_price_amount', 'upsell_headline', 'upsell_description']);
        });
    }
};
