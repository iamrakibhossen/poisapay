<?php

declare(strict_types=1);

namespace App\Domain\Chain;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\OnchainTxStatus;
use App\Enums\SweepStatus;
use App\Models\Asset;
use App\Models\DepositAddress;
use App\Models\GasWallet;
use App\Models\OnchainTx;
use App\Models\Sweep;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sweep engine (TDD §6.2). Moves swept coins out of a deposit address into the
 * pooled hot wallet — treasury-internal, never touches user balances. Ledger:
 * treasury:pending -> treasury:hot for the asset; gas is drawn from the chain's
 * gas wallet. Idempotent by nonce/context. Simulated broadcast (no live node).
 */
class SweepDepositAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(DepositAddress $address, Asset $asset, Money $amount, ?string $nonceContext = null): Sweep
    {
        if (! $amount->isPositive()) {
            throw new RuntimeException('Sweep amount must be positive.');
        }

        return DB::transaction(function () use ($address, $asset, $amount, $nonceContext): Sweep {
            $nonceContext ??= 'sweep:'.$address->id.':'.$asset->id.':'.now()->timestamp;

            $existing = Sweep::where('nonce_context', $nonceContext)->first();
            if ($existing) {
                return $existing;
            }

            $pending = $this->accounts->system(LedgerAccountType::TreasuryPending, $asset->id);
            $hot = $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id);

            // Move the pooled pending balance into the hot wallet (debit-normal accounts).
            $entry = $this->ledger->post(new EntryData(
                type: 'sweep.settle',
                idempotencyKey: 'sweep:'.$nonceContext,
                lines: [
                    PostingLine::debit($hot->id, $asset->id, $amount),
                    PostingLine::credit($pending->id, $asset->id, $amount),
                ],
                memo: "Sweep {$asset->symbol} to hot wallet",
                metadata: ['deposit_address_id' => $address->id],
            ));

            // Draw gas from the chain's gas wallet (nominal simulated cost).
            $gasCost = $this->chargeGas($address->chain_id);

            $tx = OnchainTx::create([
                'chain_id' => $address->chain_id,
                'tx_hash' => '0x'.bin2hex(random_bytes(16)),
                'log_index' => 0,
                'from_address' => $address->address,
                'asset_id' => $asset->id,
                'amount' => $amount->baseString(),
                'confirmations' => $asset->requiredConfirmations(),
                'status' => OnchainTxStatus::Confirmed,
                'direction' => 'out',
            ]);

            $sweep = Sweep::create([
                'deposit_address_id' => $address->id,
                'asset_id' => $asset->id,
                'amount' => $amount->baseString(),
                'gas_cost' => $gasCost,
                'status' => SweepStatus::Swept,
                'nonce_context' => $nonceContext,
                'settle_entry_id' => $entry->id,
                'onchain_tx_id' => $tx->id,
            ]);

            ActivityLogger::log('sweep.completed', $sweep, ['asset' => $asset->symbol, 'amount' => $amount->baseString()]);

            return $sweep;
        });
    }

    /** Deduct a nominal gas cost from the chain's gas wallet; returns the cost (base units). */
    private function chargeGas(int $chainId): string
    {
        $gas = GasWallet::where('chain_id', $chainId)->lockForUpdate()->first();
        if (! $gas) {
            return '0';
        }

        // ~0.0003 native coin per sweep (18 decimals).
        $cost = BigInteger::of('300000000000000');
        $balance = BigInteger::of($gas->balance);
        $charge = $balance->isLessThan($cost) ? $balance : $cost;
        $gas->update(['balance' => (string) $balance->minus($charge)]);

        return (string) $charge;
    }
}
