<?php

declare(strict_types=1);

namespace App\Shop\DTOs;

use App\Shop\Enums\DnsRecordType;

/** Outcome of a DNS verification pass for a custom domain. */
final readonly class DomainVerificationResult
{
    public function __construct(
        public bool $ownershipOk,
        public bool $routingOk,
        public ?DnsRecordType $recordType,
        public ?string $error,
    ) {}

    public function verified(): bool
    {
        return $this->ownershipOk && $this->routingOk;
    }
}
