<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Enums\ChainType;
use App\Models\EvmNonce;
use Illuminate\Support\Facades\DB;

/**
 * Hands out strictly increasing nonces for a hot wallet (Wave 2). Reconciles the
 * locally reserved counter with the chain's pending transaction count and takes the
 * max, so a stale local value can't reuse a nonce and concurrent signs in one tick
 * don't collide. The reservation row is locked for the duration.
 */
class NonceManager
{
    public function __construct(private readonly BlockchainProvider $chain) {}

    public function next(ChainType $chainType, string $address): int
    {
        $onchain = $this->chain->getTransactionCount($chainType, $address);

        return DB::transaction(function () use ($chainType, $address, $onchain): int {
            $row = EvmNonce::where('chain', $chainType->value)
                ->where('address', strtolower($address))
                ->lockForUpdate()
                ->first();

            if (! $row) {
                $row = EvmNonce::create([
                    'chain' => $chainType->value,
                    'address' => strtolower($address),
                    'next_nonce' => $onchain,
                ]);
            }

            $nonce = max($onchain, (int) $row->next_nonce);
            $row->update(['next_nonce' => $nonce + 1]);

            return $nonce;
        });
    }
}
