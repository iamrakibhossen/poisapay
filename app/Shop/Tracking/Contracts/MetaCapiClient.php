<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Contracts;

/**
 * Sends server-side events to Meta's Conversions API. Behind a contract so the
 * simulated driver (default, no network) can be swapped for the real HTTP driver
 * via config — wire `http` before enabling in prod.
 */
interface MetaCapiClient
{
    /**
     * POST a batch of already-built events to a pixel's CAPI endpoint.
     *
     * @param  list<array<string, mixed>>  $events  Meta "data" objects (event_name, event_time, event_id, user_data, custom_data, …)
     */
    public function send(string $pixelId, string $accessToken, array $events): void;
}
