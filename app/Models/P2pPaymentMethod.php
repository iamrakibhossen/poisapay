<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Catalog of supported fiat rails (bKash, Nagad, bank, Wise, …). Reference data
 * seeded by migration; instances configured per-user in {@see P2pUserPaymentMethod}.
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string $type
 * @property string|null $country
 * @property string|null $icon
 * @property array<int, array{key: string, label: string, required: bool}>|null $fields
 * @property bool $is_active
 * @property int $sort
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, P2pUserPaymentMethod> $userAccounts
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

    /** @return HasMany<P2pUserPaymentMethod, $this> */
    public function userAccounts(): HasMany
    {
        return $this->hasMany(P2pUserPaymentMethod::class, 'payment_method_id');
    }

    /**
     * The account field schema, falling back to a generic name+number pair when
     * an admin hasn't configured one. Shared by the payout form and order view.
     *
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    public function fieldSchema(): array
    {
        return ! empty($this->fields) ? $this->fields : [
            ['key' => 'account_name', 'label' => 'Account name', 'required' => true],
            ['key' => 'account_number', 'label' => 'Account number', 'required' => true],
        ];
    }
}
