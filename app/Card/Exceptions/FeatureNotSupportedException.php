<?php

declare(strict_types=1);

namespace App\Card\Exceptions;

use App\Card\Enums\ProviderCapability;

/** Thrown when a provider is asked for a capability it does not implement. */
class FeatureNotSupportedException extends CardProviderException
{
    public static function for(string $driver, ProviderCapability $capability): self
    {
        return new self("Provider [{$driver}] does not support [{$capability->value}].");
    }
}
