<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Reserved next-nonce per (chain, hot-wallet address) for EVM signing (Wave 2).
 *
 * @property string $chain
 * @property string $address
 * @property int $next_nonce
 */
class EvmNonce extends Model
{
    use HasUuids;

    protected $fillable = ['chain', 'address', 'next_nonce'];

    protected function casts(): array
    {
        return ['next_nonce' => 'integer'];
    }
}
