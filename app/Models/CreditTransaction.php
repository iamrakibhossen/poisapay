<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'credit_line_id', 'type', 'asset_id', 'amount', 'entry_id', 'memo',
    ];

    public function creditLine(): BelongsTo
    {
        return $this->belongsTo(CreditLine::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function money(): Money
    {
        return $this->asset->money($this->amount);
    }
}
