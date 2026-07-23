<?php

declare(strict_types=1);

namespace App\Card\Providers\Marqeta;

use App\Card\Exceptions\ProviderRequestException;
use App\Card\Support\ProviderLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin HTTP client for the Marqeta Core API. Basic auth = application_token:admin_token
 * (https://www.marqeta.com/docs/core-api/authentication). Every call is logged
 * (secrets redacted) and non-2xx responses raise ProviderRequestException.
 */
class MarqetaClient
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly ProviderLogger $logger,
        private readonly string $driver = 'marqeta',
    ) {}

    /** @param array<string, mixed> $body @param array<string, mixed> $query @return array<string, mixed> */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [], $query);
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function post(string $path, array $body = []): array
    {
        return $this->request('POST', $path, $body);
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    public function put(string $path, array $body = []): array
    {
        return $this->request('PUT', $path, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $body = [], array $query = []): array
    {
        $url = rtrim((string) $this->config['api_url'], '/').'/'.ltrim($path, '/');
        $http = config('card.http', []);

        try {
            $response = Http::withBasicAuth((string) $this->config['application_token'], (string) $this->config['admin_token'])
                ->timeout((int) ($http['timeout'] ?? 15))
                // Retry only genuine connection failures (no response = no side effect);
                // never throw on 4xx/5xx so our own handler maps status + error_code.
                ->retry((int) ($http['retry_attempts'] ?? 2), (int) ($http['retry_sleep_ms'] ?? 200), fn ($e) => $e instanceof ConnectionException, throw: false)
                ->acceptJson()
                ->send($method, $url, array_filter([
                    'query' => $query ?: null,
                    'json' => in_array($method, ['POST', 'PUT'], true) ? $body : null,
                ]));
        } catch (Throwable $e) {
            $this->log($method, $path, $body, null, null, false, $e->getMessage());

            throw new ProviderRequestException("Marqeta request failed: {$e->getMessage()}", null, null, $e);
        }

        $json = $response->json() ?? [];
        $this->log($method, $path, $body, is_array($json) ? $json : [], $response->status(), $response->successful(), $response->successful() ? null : (string) $response->body());

        if (! $response->successful()) {
            $detail = $json['error_message'] ?? mb_substr((string) $response->body(), 0, 200);
            throw new ProviderRequestException(
                "Marqeta {$method} {$path} returned {$response->status()}: {$detail}",
                $response->status(),
                (string) ($json['error_code'] ?? null),
            );
        }

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>|null  $response
     */
    private function log(string $method, string $path, array $request, ?array $response, ?int $status, bool $success, ?string $error): void
    {
        $this->logger->record([
            'driver' => $this->driver,
            'direction' => 'outbound',
            'operation' => trim($path, '/'),
            'method' => $method,
            'endpoint' => $path,
            'request' => $request ?: null,
            'response' => $response,
            'status_code' => $status,
            'success' => $success,
            'error' => $error !== null ? mb_substr($error, 0, 255) : null,
        ]);
    }
}
