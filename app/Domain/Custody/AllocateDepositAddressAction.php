<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Custody\Contracts\AddressDeriver;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\DepositAddress;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Deposit-address allocation (TDD §6.1 step 2, §4.2).
 *
 * The derivation index is allocated atomically from the xpub's monotonic
 * counter under a row lock (never MAX+1). The derived address is added to the
 * Redis watch set so the Blockchain Monitor picks up inbound funds.
 */
class AllocateDepositAddressAction
{
    public function __construct(private readonly AddressDeriver $deriver) {}

    public function execute(User $user, Chain $chain): DepositAddress
    {
        // One active address per (user, chain) is reused (§6.1 step 1).
        $existing = DepositAddress::query()
            ->where('user_id', $user->id)
            ->where('chain_id', $chain->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $chain): DepositAddress {
            /** @var CustodyXpub|null $xpub */
            $xpub = CustodyXpub::query()
                ->where('chain_id', $chain->id)
                ->where('purpose', 'deposit')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $xpub) {
                throw new RuntimeException("No active deposit xpub registered for chain {$chain->key->value}.");
            }

            $index = $xpub->next_index;
            $xpub->increment('next_index');

            $address = $this->deriver->derive($chain->key, $xpub->xpub, $index);

            $model = DepositAddress::create([
                'user_id' => $user->id,
                'chain_id' => $chain->id,
                'xpub_id' => $xpub->id,
                'derivation_index' => $index,
                'address' => $address,
                'is_watched' => true,
            ]);

            $this->addToWatchSet($chain, $address);

            return $model;
        });
    }

    private function addToWatchSet(Chain $chain, string $address): void
    {
        try {
            Cache::store('redis')->getStore()->connection()
                ->sadd("watch:{$chain->key->value}", strtolower($address));
        } catch (\Throwable) {
            // Watch set is an optimisation; the Monitor also reconciles via DB (§5.4).
        }
    }
}
