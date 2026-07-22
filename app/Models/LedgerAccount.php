<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerSide;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LedgerAccount extends Model
{
    use HasUuids;

    protected $fillable = [
        'type', 'user_id', 'asset_id', 'normal_side', 'label',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerAccountType::class,
            'normal_side' => LedgerSide::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(AccountBalance::class, 'account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(LedgerLine::class, 'account_id');
    }

    /** Current balance as a Money VO in the asset's scale. */
    public function money(): Money
    {
        $base = $this->balance?->balance ?? '0';

        return Money::ofBase($base, $this->asset->decimals, $this->asset->symbol);
    }
}
