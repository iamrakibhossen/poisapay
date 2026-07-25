<?php

declare(strict_types=1);

namespace App\Shop\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasUuids;

    protected $table = 'sell_domains';

    protected $fillable = [
        'seller_id', 'sales_page_id', 'host', 'status',
        'verification_token', 'ssl_active', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'ssl_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function salesPage(): BelongsTo
    {
        return $this->belongsTo(SalesPage::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
