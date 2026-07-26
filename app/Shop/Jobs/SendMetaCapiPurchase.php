<?php

declare(strict_types=1);

namespace App\Shop\Jobs;

use App\Shop\Models\Order;
use App\Shop\Tracking\Capi\MetaPurchaseEvent;
use App\Shop\Tracking\Contracts\MetaCapiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a paid order's Purchase to Meta's Conversions API, off the request path.
 *
 * Server-side counterpart to the browser pixel — it survives ad-blockers / iOS,
 * and shares the browser event's `event_id` (the order key) so Meta dedups the
 * pair. Retries with backoff; because the id is stable, retries never double-count.
 * The pixel id + token are snapshotted at dispatch (the listener does the gating),
 * so a later config/page change can't misroute an in-flight send.
 *
 * @param  array{url?: ?string, ip?: ?string, ua?: ?string, fbp?: ?string, fbc?: ?string}  $context
 */
class SendMetaCapiPurchase implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @param  array<string, string|null>  $context */
    public function __construct(
        private readonly string $orderId,
        private readonly string $pixelId,
        private readonly string $accessToken,
        private readonly array $context = [],
    ) {}

    public function tries(): int
    {
        return (int) config('shop.tracking.meta_capi.max_attempts', 5);
    }

    public function backoff(): int
    {
        return (int) config('shop.tracking.meta_capi.backoff', 60);
    }

    public function handle(MetaCapiClient $client): void
    {
        $order = Order::with(['buyer', 'asset', 'items'])->find($this->orderId);

        // The order may not be visible yet if this ran before its transaction
        // committed — retry a couple of times, then give up rather than fail loud.
        if ($order === null) {
            if ($this->attempts() < 3) {
                $this->release(10);
            }

            return;
        }

        $client->send($this->pixelId, $this->accessToken, [
            MetaPurchaseEvent::build($order, $this->context),
        ]);
    }
}
