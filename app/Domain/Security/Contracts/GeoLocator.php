<?php

declare(strict_types=1);

namespace App\Domain\Security\Contracts;

use App\Domain\Security\DTO\GeoLocation;

/**
 * IP geolocation provider. The stub resolves nothing (no fabricated data); a real
 * vendor (MaxMind GeoIP2, ipapi, ipinfo) implements this and registers a driver in
 * config/providers.php. Consumers (login risk, country-risk checks) treat an
 * unknown location as neutral.
 */
interface GeoLocator
{
    /** Stable provider identifier. */
    public function name(): string;

    public function locate(string $ip): GeoLocation;
}
