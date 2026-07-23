<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property int $asset_id
 * @property string $amount
 * @property string|null $fee
 * @property int $confirmations
 * @property int $required_confirmations
 * @property DepositStatus $status
 * @property-read Asset $asset
 * @property-read OnchainTx|null $onchainTx
 */
class Deposit extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'deposit_address_id', 'deposit_method_id', 'asset_id', 'source', 'onchain_tx_id',
        'reference', 'amount', 'fee', 'confirmations', 'required_confirmations', 'status', 'credit_entry_id', 'credited_at',
    ];

    public function depositMethod(): BelongsTo
    {
        return $this->belongsTo(DepositMethod::class);
    }

    protected function casts(): array
    {
        return [
            'status' => DepositStatus::class,
            'confirmations' => 'integer',
            'required_confirmations' => 'integer',
            'credited_at' => 'datetime',
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

    public function depositAddress(): BelongsTo
    {
        return $this->belongsTo(DepositAddress::class);
    }

    public function onchainTx(): BelongsTo
    {
        return $this->belongsTo(OnchainTx::class, 'onchain_tx_id');
    }

    public function money(): Money
    {
        return $this->asset->money($this->amount);
    }

    /** Platform fee taken from this deposit. */
    public function feeMoney(): Money
    {
        return $this->asset->money($this->fee ?? '0');
    }

    /** Amount actually credited to the user (gross − fee). */
    public function netMoney(): Money
    {
        return $this->money()->minus($this->feeMoney());
    }
}
