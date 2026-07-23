<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\WebhookLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records every inbound webhook request + our response into webhook_logs for audit,
 * debugging and replay. The write happens in terminate() — AFTER the response is sent
 * to the caller — so it adds zero latency to the (time-critical) webhook response and
 * needs no queue worker. Payload is content-hashed for dedup and secret-bearing
 * headers are redacted before storage.
 */
class WebhookLogger
{
    /** Header names whose values are secrets and must never be persisted. */
    private const REDACT_HEADERS = [
        'authorization', 'stripe-signature', 'x-marqeta-signature',
        'x-signature', 'x-hub-signature', 'x-hub-signature-256', 'cookie',
    ];

    private const MAX_RESPONSE = 4000;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /** Runs after the response is dispatched to the caller. */
    public function terminate(Request $request, Response $response): void
    {
        try {
            // Normalise the payload — parsed input, or the raw body for non-form posts.
            $payload = $request->all();
            if ($payload === [] && ! $request->isJson()) {
                $payload = ['body' => $request->getContent()];
            }
            ksort($payload);

            $hash = md5((string) json_encode($payload));
            $resolved = $response->getStatusCode() < 400;

            // Collapse earlier unresolved duplicates of the same payload once one succeeds.
            if ($resolved) {
                WebhookLog::where('hash', $hash)->where('resolved', false)->update(['resolved' => true]);
            }

            WebhookLog::create([
                'provider' => (string) ($request->route('provider') ?? $request->route('driver') ?? ''),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => $request->route()?->getName(),
                'payload' => $payload,
                'headers' => $this->redact($request->headers->all()),
                'ip' => $request->ip(),
                'hash' => $hash,
                'status' => $response->getStatusCode(),
                'response' => mb_substr((string) $response->getContent(), 0, self::MAX_RESPONSE),
                'resolved' => $resolved,
            ]);
        } catch (Throwable $e) {
            // Logging must never break webhook handling.
            report($e);
        }
    }

    /**
     * @param  array<string, array<int, string|null>>  $headers
     * @return array<string, array<int, string|null>>
     */
    private function redact(array $headers): array
    {
        foreach ($headers as $name => $values) {
            if (in_array(strtolower((string) $name), self::REDACT_HEADERS, true)) {
                $headers[$name] = ['[redacted]'];
            }
        }

        return $headers;
    }
}
