<?php

declare(strict_types=1);

namespace App\Domain\Security\DTO;

/**
 * Neutral geolocation of an IP, independent of the vendor. Coordinates are
 * optional (the stub returns none rather than fabricating data); impossible-travel
 * checks degrade gracefully when they're absent.
 */
final class GeoLocation
{
    /**
     * @param  string|null  $country  ISO-3166 alpha-2
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $ip,
        public readonly ?string $country = null,
        public readonly ?string $region = null,
        public readonly ?string $city = null,
        public readonly ?float $lat = null,
        public readonly ?float $lon = null,
        public readonly array $raw = [],
    ) {}

    public function isKnown(): bool
    {
        return $this->country !== null;
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lon !== null;
    }

    public static function unknown(string $ip): self
    {
        return new self($ip);
    }

    /** Great-circle distance in km (Haversine), or null if either point lacks coordinates. */
    public function distanceKmTo(GeoLocation $other): ?float
    {
        if (! $this->hasCoordinates() || ! $other->hasCoordinates()) {
            return null;
        }

        $earthKm = 6371.0;
        $dLat = deg2rad($other->lat - $this->lat);
        $dLon = deg2rad($other->lon - $this->lon);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->lat)) * cos(deg2rad($other->lat)) * sin($dLon / 2) ** 2;

        return $earthKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
