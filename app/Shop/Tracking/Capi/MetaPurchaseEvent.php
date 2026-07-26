<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Capi;

use App\Shop\Models\Order;

/**
 * Builds Meta's Conversions API "data" object for a paid order's Purchase event.
 *
 * `event_id` is the order key — the SAME id the browser pixel sends — so Meta
 * dedups the two and counts one conversion (and job retries stay idempotent).
 * PII (email) is SHA-256 hashed per Meta's requirement; ip/ua/fbp/fbc are sent
 * raw as Meta expects. Only non-empty keys are included.
 */
final class MetaPurchaseEvent
{
    /**
     * @param  array{url?: ?string, ip?: ?string, ua?: ?string, fbp?: ?string, fbc?: ?string}  $context
     * @return array<string, mixed>
     */
    public static function build(Order $order, array $context = []): array
    {
        $order->loadMissing('buyer', 'asset', 'items');

        $email = $order->buyer?->email;
        $currency = $order->asset->currency_code ?: $order->asset->symbol;
        $contentIds = $order->items
            ->pluck('product_id')->filter()->map(static fn ($id) => (string) $id)->unique()->values()->all();

        // array_filter default drops null / '' / [] — em stays (non-empty hash array).
        $userData = array_filter([
            'em' => $email ? [hash('sha256', mb_strtolower(trim($email)))] : null,
            'client_ip_address' => $context['ip'] ?? null,
            'client_user_agent' => $context['ua'] ?? null,
            'fbp' => $context['fbp'] ?? null,
            'fbc' => $context['fbc'] ?? null,
        ]);

        $customData = array_filter([
            'currency' => $currency,
            'value' => (float) $order->asset->money((string) $order->total_amount)->toDecimal(),
            'content_type' => 'product',
            'content_ids' => $contentIds,
            'order_id' => (string) $order->getKey(),
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);

        return array_filter([
            'event_name' => 'Purchase',
            'event_time' => ($order->paid_at ?? $order->created_at)?->getTimestamp(),
            'event_id' => (string) $order->getKey(),
            'action_source' => 'website',
            'event_source_url' => $context['url'] ?? null,
            'user_data' => $userData,
            'custom_data' => $customData,
        ], static fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
