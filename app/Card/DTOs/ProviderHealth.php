<?php

declare(strict_types=1);

namespace App\Card\DTOs;

/** Result of a provider healthCheck(). */
final readonly class ProviderHealth
{
    public function __construct(
        public string $provider,
        public bool $healthy,
        public ?int $latencyMs = null,
        public ?string $message = null,
    ) {}

    public static function up(string $provider, ?int $latencyMs = null): self
    {
        return new self($provider, true, $latencyMs);
    }

    public static function down(string $provider, string $message): self
    {
        return new self($provider, false, null, $message);
    }
}
