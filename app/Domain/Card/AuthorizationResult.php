<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Models\CardAuthorization;

/** The APPROVE / DECLINE decision returned to the network within the timeout. */
final readonly class AuthorizationResult
{
    private function __construct(
        public bool $approved,
        public ?string $reason = null,
        public ?CardAuthorization $authorization = null,
    ) {}

    public static function approve(CardAuthorization $auth): self
    {
        return new self(true, null, $auth);
    }

    public static function decline(string $reason): self
    {
        return new self(false, $reason);
    }

    /**
     * User-facing decline text. The machine `reason` code is what the network gets;
     * this is what a cardholder sees. A depleted wallet (no funding source, or the
     * chosen one can't cover the charge) reads simply as "Insufficient balance."
     */
    public function message(): ?string
    {
        return match ($this->reason) {
            null => null,
            'insufficient_funds', 'no_funding_asset' => 'Insufficient balance.',
            'card_not_active' => 'This card is not active.',
            'account_frozen' => 'This account is frozen.',
            'per_tx_limit_exceeded', 'daily_limit_exceeded' => 'This transaction exceeds your card limit.',
            default => 'Transaction declined.',
        };
    }

    public function toResponse(): array
    {
        return array_filter([
            'decision' => $this->approved ? 'approve' : 'decline',
            'reason' => $this->reason,
            'authorization_id' => $this->authorization?->id,
        ], fn ($v) => ! is_null($v));
    }
}
