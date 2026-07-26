<?php

declare(strict_types=1);

namespace App\Shop\Listeners;

use App\Shop\Events\OrderPlaced;
use App\Shop\Jobs\SendMetaCapiPurchase;

/**
 * On a paid order, queue the server-side Meta Purchase — but only when the order's
 * sales page has opted into the Conversions API (Meta enabled + a CAPI token). The
 * pixel id/token and the buyer's request context (url/ip/ua/_fbp/_fbc) are captured
 * here and handed to the queued job, which is the only network-touching part.
 */
class SendMetaCapiPurchaseEvent
{
    public function handle(OrderPlaced $event): void
    {
        $page = $event->order->salesPage;
        $meta = is_array($page?->tracking['meta'] ?? null) ? $page->tracking['meta'] : [];

        // Opt-in gate: browser pixel is always on; CAPI needs an explicit token.
        if (empty($meta['enabled']) || empty($meta['pixel_id']) || empty($meta['access_token'])) {
            return;
        }

        $request = request();
        $cookie = static fn (string $k): ?string => is_string($v = $request->cookie($k)) ? $v : null;
        $context = [
            'url' => $page ? route('funnel.sales', ['slug' => $page->slug]) : null,
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
            'fbp' => $cookie('_fbp'),
            'fbc' => $cookie('_fbc'),
        ];

        SendMetaCapiPurchase::dispatch(
            (string) $event->order->getKey(),
            (string) $meta['pixel_id'],
            (string) $meta['access_token'],
            $context,
        );
    }
}
