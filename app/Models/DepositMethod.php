<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositMethodType;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositMethod extends Model
{
    use HasUuids;

    protected $fillable = [
        'asset_id', 'name', 'type', 'details', 'instructions',
        'min_amount', 'max_amount', 'fixed_fee', 'percent_fee_bps', 'logo', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'type' => DepositMethodType::class,
            'details' => 'array',
            'is_active' => 'boolean',
            'percent_fee_bps' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function minMoney(): Money
    {
        return $this->asset->money($this->min_amount ?? '0');
    }

    public function maxMoney(): ?Money
    {
        return $this->max_amount !== null ? $this->asset->money($this->max_amount) : null;
    }

    /** Total fee (base units) for a deposit of the given amount. */
    public function feeFor(Money $amount): Money
    {
        $fixed = $this->asset->money($this->fixed_fee ?? '0');
        $percent = $this->asset->money(
            BigInteger::of($amount->baseString())->multipliedBy($this->percent_fee_bps)->dividedBy(10_000)
        );

        return $fixed->plus($percent);
    }
}
