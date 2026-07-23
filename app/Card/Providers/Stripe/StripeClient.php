<?php

declare(strict_types=1);

namespace App\Card\Providers\Stripe;

use App\Card\Support\ProviderLogger;
use Stripe\StripeClient as StripeSdk;
use Throwable;

/**
 * Thin wrapper over the Stripe PHP SDK for the Stripe card driver — the single place
 * that constructs a Stripe SDK client (from the driver's config slice) and records
 * outbound calls via {@see ProviderLogger}. Mirrors {@see \App\Card\Providers\Marqeta\MarqetaClient}.
 */
final class StripeClient
{
    private ?StripeSdk $sdk = null;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly ProviderLogger $logger,
        private readonly string $driver,
    ) {}

    public function sdk(): StripeSdk
    {
        if ($this->sdk === null) {
            $opts = [];
            if (($secret = (string) ($this->config['secret_key'] ?? '')) !== '') {
                $opts['api_key'] = $secret;
            }
            if ($version = $this->config['api_version'] ?? null) {
                $opts['stripe_version'] = $version;
            }
            $this->sdk = new StripeSdk($opts);
        }

        return $this->sdk;
    }

    /** A cheap authenticated call used for health checks. */
    public function ping(): void
    {
        $start = microtime(true);
        try {
            $this->sdk()->balance->retrieve();
            $this->log('ping', 200, true, null, $start);
        } catch (Throwable $e) {
            $this->log('ping', 0, false, $e->getMessage(), $start);
            throw $e;
        }
    }

    private function log(string $operation, int $status, bool $success, ?string $error, float $start): void
    {
        $this->logger->record([
            'driver' => $this->driver,
            'direction' => 'outbound',
            'operation' => $operation,
            'status_code' => $status,
            'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            'success' => $success,
            'error' => $error,
        ]);
    }
}
