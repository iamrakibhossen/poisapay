<?php

declare(strict_types=1);

namespace App\Shop\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A funnel event (page_view | checkout_start | purchase | …). Append-only; the
 * seller dashboard aggregates these over a time window. No updated_at — events
 * are immutable once written.
 */
class AnalyticsEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'sell_analytics_events';

    protected $fillable = [
        'seller_id', 'sales_page_id', 'order_id', 'type',
        'session_id', 'ip_hash', 'referrer', 'utm', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['utm' => 'array', 'occurred_at' => 'datetime'];
    }

    public function salesPage(): BelongsTo
    {
        return $this->belongsTo(SalesPage::class);
    }
}
