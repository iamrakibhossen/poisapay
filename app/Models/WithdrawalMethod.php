<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operator-configured fiat payout rail for a currency (bank / mobile wallet).
 * The set of methods a user sees when cashing out is driven by this table, so
 * withdrawal options are dynamic per currency.
 */
class WithdrawalMethod extends Model
{
    use HasUuids;

    protected $fillable = [
        'asset_id', 'name', 'type', 'details', 'instructions',
        'min_amount', 'max_amount', 'fixed_fee', 'percent_fee_bps', 'logo', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'is_active' => 'boolean',
            'percent_fee_bps' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** bank rails need a bank name from the user; mobile rails do not. */
    public function isBank(): bool
    {
        return $this->type === 'bank';
    }

    public function minMoney(): Money
    {
        return $this->asset->money($this->min_amount ?? '0');
    }

    public function maxMoney(): ?Money
    {
        return $this->max_amount !== null ? $this->asset->money($this->max_amount) : null;
    }

    /** Total fee (base units) for a payout of the given amount. */
    public function feeFor(Money $amount): Money
    {
        $fixed = $this->asset->money($this->fixed_fee ?? '0');
        $percentBase = BigInteger::of($amount->baseString())
            ->multipliedBy($this->percent_fee_bps)
            ->dividedBy(10_000, RoundingMode::DOWN);

        return $fixed->plus($this->asset->money((string) $percentBase));
    }
}
