<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'collateral_asset_id', 'principal_asset_id', 'collateral_amount',
        'principal_drawn', 'accrued_fee', 'ltv_bps', 'max_ltv_bps',
        'liquidation_ltv_bps', 'interest_apr_bps', 'status', 'last_accrued_at',
    ];

    protected function casts(): array
    {
        return [
            'ltv_bps' => 'integer',
            'max_ltv_bps' => 'integer',
            'liquidation_ltv_bps' => 'integer',
            'status' => CreditStatus::class,
            'last_accrued_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collateralAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'collateral_asset_id');
    }

    public function principalAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'principal_asset_id');
    }
}
