<?php

declare(strict_types=1);

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable exact-money value object (TDD D3, §7.3).
 *
 * All amounts are stored and computed as integer BASE UNITS (wei/sun/paisa) —
 * never floats. `decimals` is carried only so the value can be formatted for
 * display at the edge. Arithmetic is delegated to brick/math BigInteger, so
 * there is no precision loss regardless of magnitude (NUMERIC(78,0) on chain).
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public BigInteger $base,
        public int $decimals,
        public string $symbol,
    ) {
        if ($decimals < 0) {
            throw new InvalidArgumentException('Decimals cannot be negative.');
        }
    }

    /** Build from an integer base-unit amount (string|int|BigInteger). */
    public static function ofBase(BigInteger|string|int $base, int $decimals, string $symbol = ''): self
    {
        return new self(BigInteger::of((string) $base), $decimals, $symbol);
    }

    /** Build from a human decimal amount ("12.5") scaling up by `decimals`. */
    public static function ofDecimal(BigDecimal|string|int|float $amount, int $decimals, string $symbol = ''): self
    {
        $decimal = BigDecimal::of(is_float($amount) ? sprintf('%.'.$decimals.'F', $amount) : (string) $amount);
        $base = $decimal->withPointMovedRight($decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger();

        return new self($base, $decimals, $symbol);
    }

    public static function zero(int $decimals, string $symbol = ''): self
    {
        return new self(BigInteger::zero(), $decimals, $symbol);
    }

    public function plus(self $other): self
    {
        $this->assertSameScale($other);

        return new self($this->base->plus($other->base), $this->decimals, $this->symbol);
    }

    public function minus(self $other): self
    {
        $this->assertSameScale($other);

        return new self($this->base->minus($other->base), $this->decimals, $this->symbol);
    }

    /** Multiply base units by an integer factor (e.g. quantity). */
    public function multipliedBy(int|string $factor): self
    {
        return new self($this->base->multipliedBy($factor), $this->decimals, $this->symbol);
    }

    public function negated(): self
    {
        return new self($this->base->negated(), $this->decimals, $this->symbol);
    }

    public function isZero(): bool
    {
        return $this->base->isZero();
    }

    public function isNegative(): bool
    {
        return $this->base->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->base->isGreaterThan(0);
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        $this->assertSameScale($other);

        return $this->base->isGreaterThanOrEqualTo($other->base);
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameScale($other);

        return $this->base->isLessThan($other->base);
    }

    public function equals(self $other): bool
    {
        return $this->decimals === $other->decimals && $this->base->isEqualTo($other->base);
    }

    /** Human-readable decimal string (e.g. "12.500000"). */
    public function toDecimal(): string
    {
        return (string) BigDecimal::ofUnscaledValue($this->base, $this->decimals);
    }

    /**
     * Formatted for display with thousands separators and symbol. Trailing zeros are
     * trimmed but a minimum of 2 decimals is kept, so "1000.00000000" shows as
     * "1000.00" while "0.02048275" shows in full.
     */
    public function format(?int $displayDecimals = null): string
    {
        $decimal = BigDecimal::ofUnscaledValue($this->base, $this->decimals);
        $scale = $displayDecimals ?? min($this->decimals, 8);
        $rounded = $decimal->toScale($scale, RoundingMode::DOWN);

        [$whole, $frac] = array_pad(explode('.', (string) $rounded->abs()), 2, '');

        // Keep only significant decimals, padded up to a 2-decimal minimum.
        $minFrac = min(2, $scale);
        $frac = rtrim($frac, '0');
        if (strlen($frac) < $minFrac) {
            $frac = str_pad($frac, $minFrac, '0');
        }

        $grouped = number_format((float) $whole, 0, '.', ',');
        $sign = $this->base->isNegative() ? '-' : '';
        $value = $frac === '' ? $grouped : "{$grouped}.{$frac}";

        return trim("{$sign}{$value} {$this->symbol}");
    }

    public function baseString(): string
    {
        return (string) $this->base;
    }

    private function assertSameScale(self $other): void
    {
        if ($this->decimals !== $other->decimals) {
            throw new InvalidArgumentException(
                "Cannot combine money of differing scale ({$this->decimals} vs {$other->decimals})."
            );
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'base' => $this->baseString(),
            'decimals' => $this->decimals,
            'symbol' => $this->symbol,
            'display' => $this->toDecimal(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
