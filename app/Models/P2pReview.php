<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\P2p\MerchantStatsService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single buyer↔seller feedback entry on a completed P2P order. The ratee's
 * cached reputation aggregates are derived from these rows by
 * {@see MerchantStatsService::recordReview()}.
 *
 * @property string $id
 * @property string $order_id
 * @property string $rater_id
 * @property string $ratee_id
 * @property int $rating
 * @property bool $is_positive
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read P2pOrder $order
 * @property-read User $rater
 * @property-read User $ratee
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

    /** @return BelongsTo<P2pOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(P2pOrder::class, 'order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    /** @return BelongsTo<User, $this> */
    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }
}
