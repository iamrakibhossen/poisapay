<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RampDirection;
use App\Enums\RampRail;
use App\Enums\RampStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property RampDirection $direction
 * @property RampRail $rail
 * @property int $fiat_asset_id
 * @property string $fiat_amount
 * @property string|null $provider_ref
 * @property string|null $beneficiary
 * @property RampStatus $status
 * @property string|null $entry_id
 * @property string|null $idempotency_key
 * @property-read User $user
 * @property-read Asset $fiatAsset
 * @property-read JournalEntry|null $entry
 */
class RampOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'direction', 'rail', 'fiat_asset_id', 'fiat_amount',
        'provider_ref', 'beneficiary', 'status', 'entry_id', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'direction' => RampDirection::class,
            'rail' => RampRail::class,
            'status' => RampStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fiatAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'fiat_asset_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'entry_id');
    }
}
