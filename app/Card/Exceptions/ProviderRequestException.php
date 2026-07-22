<?php

declare(strict_types=1);

namespace App\Card\Exceptions;

use Throwable;

/** A provider API call failed (network, non-2xx, malformed response). */
class ProviderRequestException extends CardProviderException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
