<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Capi;

use App\Shop\Tracking\Contracts\MetaCapiClient;
use Illuminate\Support\Facades\Log;

/**
 * Default no-network driver: records intent to the log instead of calling Meta.
 * Keeps prod safe until a real token + `http` driver are configured, and lets
 * tests assert dispatch without hitting the Graph API.
 */
final class SimulatedMetaCapiClient implements MetaCapiClient
{
    public function send(string $pixelId, string $accessToken, array $events): void
    {
        Log::info('Meta CAPI (simulated) — would send events', [
            'pixel_id' => $pixelId,
            'events' => array_map(static fn ($e) => $e['event_name'] ?? '?', $events),
            'count' => count($events),
        ]);
    }
}
