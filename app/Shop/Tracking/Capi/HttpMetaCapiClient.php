<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Capi;

use App\Shop\Tracking\Contracts\MetaCapiClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real Conversions API driver — POSTs a batch to
 * https://graph.facebook.com/{version}/{pixel_id}/events. Throws on a non-2xx so
 * the queued job retries with backoff. Meta dedups against the browser pixel by
 * the shared `event_id`, so retries never double-count.
 */
final class HttpMetaCapiClient implements MetaCapiClient
{
    public function send(string $pixelId, string $accessToken, array $events): void
    {
        if ($events === []) {
            return;
        }

        $version = (string) config('shop.tracking.meta_capi.api_version', 'v21.0');
        $payload = ['data' => $events, 'access_token' => $accessToken];

        if ($code = config('shop.tracking.meta_capi.test_event_code')) {
            $payload['test_event_code'] = $code;
        }

        $response = Http::asJson()
            ->timeout(10)
            ->post("https://graph.facebook.com/{$version}/{$pixelId}/events", $payload);

        if ($response->failed()) {
            throw new RuntimeException("Meta CAPI send failed ({$response->status()}): ".$response->body());
        }
    }
}
