<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\P2p\MerchantStatsService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single buyer↔seller feedback entry on a completed P2P order. The ratee's
 * cached reputation aggregates are derived from these rows by
 * {@see MerchantStatsService::recordReview()}.
 */
class P2pReview extends Model
{
    use HasUuids;

    protected $table = 'p2p_reviews';

    protected $fillable = [
        'order_id', 'rater_id', 'ratee_id', 'rating', 'is_positive', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_positive' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }
}
