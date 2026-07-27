<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardAuthStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardAuthorization extends Model
{
    use HasUuids;

    protected $fillable = [
        'card_id', 'network_auth_id', 'amount', 'currency_code', 'mcc', 'merchant',
        'funding_asset_id', 'held_amount', 'quote_id', 'status',
        'hold_entry_id', 'settle_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => CardAuthStatus::class,
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function fundingAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'funding_asset_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(FxQuote::class, 'quote_id');
    }

    public function holdEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'hold_entry_id');
    }

    public function settleEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'settle_entry_id');
    }

    /**
     * True when the charge was funded from crypto and auto-converted to the
     * merchant's fiat currency (vs. spent directly from a matching fiat wallet).
     */
    public function fundedByConversion(): bool
    {
        /** @var Asset|null $asset */
        $asset = $this->fundingAsset;

        return $asset !== null
            && $asset->isFiat() === false
            && strtoupper((string) $asset->symbol) !== strtoupper((string) $this->currency_code);
    }

    /**
     * Human funding-source label for transaction history:
     *  - fiat wallet   → "GBP Wallet"
     *  - USDT-funded   → "34.18 USDT"
     */
    public function fundingSourceLabel(): string
    {
        /** @var Asset|null $asset */
        $asset = $this->fundingAsset;
        if ($asset === null) {
            return '—';
        }
        if ($this->fundedByConversion()) {
            $amount = rtrim(rtrim($asset->money((string) $this->held_amount)->toDecimal(), '0'), '.');

            return $amount.' '.$asset->symbol;
        }

        return strtoupper((string) $this->currency_code).' '.__('Wallet');
    }

    /**
     * Effective conversion rate label for a crypto-funded charge, e.g.
     * "1 USDT = 0.7312 GBP", or null when no conversion happened.
     */
    public function exchangeRateLabel(): ?string
    {
        /** @var Asset|null $asset */
        $asset = $this->fundingAsset;
        if ($asset === null || ! $this->fundedByConversion()) {
            return null;
        }

        $crypto = BigDecimal::ofUnscaledValue($asset->money((string) $this->held_amount)->baseString(), $asset->decimals);
        if ($crypto->isZero()) {
            return null;
        }

        $fiat = BigDecimal::of((string) $this->amount)->withPointMovedLeft(2); // minor units → whole
        $rate = $fiat->dividedBy($crypto, 4, RoundingMode::HALF_UP);

        return '1 '.$asset->symbol.' = '.rtrim(rtrim((string) $rate, '0'), '.').' '.strtoupper((string) $this->currency_code);
    }
}
