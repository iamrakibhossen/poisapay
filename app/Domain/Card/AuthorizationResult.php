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

    public function toResponse(): array
    {
        return array_filter([
            'decision' => $this->approved ? 'approve' : 'decline',
            'reason' => $this->reason,
            'authorization_id' => $this->authorization?->id,
        ], fn ($v) => ! is_null($v));
    }
}
