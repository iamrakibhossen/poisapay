<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed enums so a single enum definition can power
 * Filament badges, Livewire status labels and API serialisation without
 * duplicating label/colour maps across the codebase (DRY).
 */
trait HasMeta
{
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => (string) $case->value, self::cases());
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[(string) $case->value] = $case->label();
        }

        return $out;
    }

    public function is(self $other): bool
    {
        return $this === $other;
    }

    /** @param  array<int, self>  $others */
    public function in(array $others): bool
    {
        return in_array($this, $others, true);
    }

    public function label(): string
    {
        return str($this->name)->lower()->replace('_', ' ')->title()->toString();
    }

    /** Tailwind/Filament colour token; overridden per enum where meaningful. */
    public function color(): string
    {
        return 'gray';
    }
}
