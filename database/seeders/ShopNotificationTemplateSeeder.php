<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * Admin-editable notification templates for the Shop module. Idempotent
 * (firstOrCreate) and faker-free so it runs safely on staging/prod. Keys are
 * resolved by NotificationService::send(); {{tokens}} are filled from the payload.
 */
class ShopNotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // [key, name, category, channels, subject, body]
        $templates = [
            ['shop.order.created', 'Order confirmed', 'money', ['in_app', 'email'], 'Order confirmed', 'Your order {{number}} for {{product}} is confirmed. Total {{amount}}.'],
            ['shop.purchase.new', 'New sale', 'money', ['in_app', 'email'], 'New sale', 'You sold {{product}} for {{amount}} (order {{number}}).'],
            ['shop.order.completed', 'Order completed', 'money', ['in_app', 'email'], 'Order completed', 'Your order {{number}} is complete.'],
            ['shop.order.cancelled', 'Order cancelled', 'money', ['in_app', 'email'], 'Order cancelled', 'Your order {{number}} was cancelled.'],
            ['shop.order.shipped', 'Order shipped', 'product', ['in_app', 'email'], 'Order shipped', 'Your order {{number}} has shipped.'],
            ['shop.refund.requested', 'Refund requested', 'money', ['in_app', 'email'], 'Refund requested', 'A refund was requested on order {{number}} ({{amount}}).'],
            ['shop.refund.approved', 'Refund approved', 'money', ['in_app', 'email'], 'Refund approved', 'Your refund of {{amount}} on order {{number}} was approved.'],
            ['shop.refund.rejected', 'Refund declined', 'money', ['in_app', 'email'], 'Refund declined', 'Your refund request on order {{number}} was declined.'],
            ['shop.review.submitted', 'New review', 'product', ['in_app', 'email'], 'New review', '{{buyer}} left a {{rating}}-star review on {{product}}.'],
            ['shop.product.published', 'Product published', 'product', ['in_app', 'email'], 'Product published', 'Your product {{product}} is now live.'],
            ['shop.product.disabled', 'Product disabled', 'product', ['in_app', 'email'], 'Product disabled', 'Your product {{product}} was disabled.'],
            ['shop.seller.applied', 'Application received', 'product', ['in_app', 'email'], 'Application received', 'We received your shop application and will review it shortly.'],
            ['shop.seller.approved', 'Shop approved', 'product', ['in_app', 'email'], 'Shop approved', 'Your shop is verified — you can start selling.'],
            ['shop.seller.rejected', 'Application declined', 'product', ['in_app', 'email'], 'Application declined', 'Your shop application was not approved: {{reason}}.'],
            ['shop.seller.suspended', 'Shop suspended', 'security', ['in_app', 'email'], 'Shop suspended', 'Your shop has been suspended. Please contact support.'],
            ['shop.balance.low', 'Low balance', 'money', ['in_app', 'email'], 'Low balance', 'Your available balance is low: {{amount}}.'],
        ];

        foreach ($templates as [$key, $name, $category, $channels, $subject, $body]) {
            NotificationTemplate::firstOrCreate(
                ['key' => $key, 'locale' => 'en'],
                ['name' => $name, 'category' => $category, 'channels' => $channels, 'subject' => $subject, 'body' => $body, 'is_active' => true],
            );
        }
    }
}
