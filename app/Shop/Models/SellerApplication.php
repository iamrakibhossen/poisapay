<?php

declare(strict_types=1);

namespace App\Shop\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable record of one seller-application submission and its decision. */
class SellerApplication extends Model
{
    use HasUuids;

    protected $table = 'shop_seller_applications';

    protected $fillable = [
        'seller_id', 'snapshot', 'status', 'submitted_at', 'decided_by', 'decided_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
