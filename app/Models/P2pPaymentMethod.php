<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog of supported fiat rails (bKash, Nagad, bank, Wise, …). Reference data
 * seeded by migration; instances configured per-user in {@see P2pUserPaymentMethod}.
 */
class P2pPaymentMethod extends Model
{
    use HasUuids;

    protected $table = 'p2p_payment_methods';

    protected $fillable = [
        'key', 'name', 'type', 'country', 'icon', 'fields', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function userAccounts(): HasMany
    {
        return $this->hasMany(P2pUserPaymentMethod::class, 'payment_method_id');
    }
}
