<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Asset;
use App\Models\User;

/**
 * Resolves the fiat currency the consumer app values balances in. A user's own
 * `base_currency` wins; otherwise the platform default (admin "Base Currency"
 * setting) applies. Selectable options come from the real fiat assets in the
 * catalogue, so a currency only appears once its asset exists.
 */
class BaseCurrency
{
    /** Fiat codes the public rate feed can price (matches CoinGeckoRateProvider::FIATS). */
    private const DISPLAY_FIATS = ['USD', 'BDT', 'EUR'];

    /** Common currency glyphs; falls back to the code itself. */
    private const SYMBOLS = ['USD' => '$', 'EUR' => '€', 'BDT' => '৳', 'GBP' => '£'];

    /** Platform-wide default fiat code (admin General setting). */
    public static function code(): string
    {
        return (string) getSetting('base_currency', 'BDT');
    }

    /**
     * Currency the public rate widgets (converter / prices) display in: the platform
     * "Base Currency" setting ({@see code()}), restricted to fiats the feed can price
     * (else USD). These marketing surfaces show the SITE base currency, not any
     * signed-in viewer's personal choice.
     */
    public static function displayCode(): string
    {
        $code = strtoupper(self::code());

        return in_array($code, self::DISPLAY_FIATS, true) ? $code : 'USD';
    }

    /** Display glyph for a currency code (defaults to the current display currency). */
    public static function symbol(?string $code = null): string
    {
        $code = strtoupper($code ?? self::displayCode());

        return self::SYMBOLS[$code] ?? $code;
    }

    /** The fiat Asset a user's balances are valued in: their choice, else the platform default. */
    public static function assetFor(User $user): ?Asset
    {
        $code = $user->base_currency ?: self::code();

        return Asset::where('kind', 'fiat')->where('currency_code', $code)->first()
            ?? Asset::where('kind', 'fiat')->where('currency_code', self::code())->first();
    }

    /**
     * Selectable base currencies, built from real fiat assets.
     *
     * @return array<string, string> currency_code => "CODE — Name"
     */
    public static function options(): array
    {
        return Asset::where('kind', 'fiat')->whereNotNull('currency_code')
            ->orderBy('sort')->get()
            ->unique('currency_code')
            ->mapWithKeys(fn (Asset $a) => [$a->currency_code => "{$a->currency_code} — {$a->name}"])
            ->all();
    }
}
