<?php

declare(strict_types=1);

namespace App\Card\DTOs;

/**
 * A short-lived, single-card-scoped handshake that lets the USER'S BROWSER fetch
 * full card details directly from the issuer (PCI SAQ-A). It never carries a PAN
 * from a real provider — only the client secret the browser needs to talk to the
 * issuer itself (Stripe ephemeral key). The `pan`/`cvv` fields are populated ONLY
 * by the simulated (mock) provider for local development, never by a live issuer.
 */
final readonly class RevealSession
{
    public function __construct(
        public string $driver,                    // 'stripe' | 'mock' — frontend picks how to render
        public string $providerCardRef,           // issuer card id (e.g. Stripe ic_…) — used client-side by Stripe.js
        public ?string $ephemeralKeySecret = null, // Stripe ephemeral key secret; NOT the PAN
        public ?int $expiresAt = null,            // epoch seconds the session is valid until
        public ?string $last4 = null,             // masked chrome / mock display
        public ?int $expMonth = null,
        public ?int $expYear = null,
        public ?string $pan = null,               // simulated provider only — never a live PAN
        public ?string $cvv = null,               // simulated provider only
    ) {}

    /** JSON payload for the reveal endpoint. Contains no secret_key/publishable_key. */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'card' => $this->providerCardRef,
            'ephemeralKeySecret' => $this->ephemeralKeySecret,
            'expiresAt' => $this->expiresAt,
            'last4' => $this->last4,
            'expMonth' => $this->expMonth,
            'expYear' => $this->expYear,
            'pan' => $this->pan,
            'cvv' => $this->cvv,
        ];
    }
}
