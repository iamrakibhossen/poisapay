<?php

declare(strict_types=1);

namespace App\Domain\Security\Geo;

use App\Domain\Security\Contracts\GeoLocator;
use App\Domain\Security\DTO\GeoLocation;

/**
 * Default geolocator. Makes no external calls and fabricates no data — it returns
 * an unknown location for every IP. Country-risk and impossible-travel checks
 * treat unknown as neutral, so the platform behaves correctly until a real vendor
 * (MaxMind, ipapi) is wired in via config/providers.php.
 */
final class StubGeoLocator implements GeoLocator
{
    public function name(): string
    {
        return 'stub';
    }

    public function locate(string $ip): GeoLocation
    {
        return GeoLocation::unknown($ip);
    }
}
