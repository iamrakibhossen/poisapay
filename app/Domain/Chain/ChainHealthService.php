<?php

declare(strict_types=1);

namespace App\Domain\Chain;

use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Deposit;
use App\Models\GasWallet;
use App\Models\LedgerLine;
use App\Models\ReconciliationRun;
use App\Models\RpcEndpoint;
use App\Models\Sweep;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Collection;

/**
 * Blockchain health (TDD §11.5 observability). Simulated node/RPC health checks
 * — stands in for real `eth_blockNumber`/latency probes — plus a per-chain
 * operational summary (RPC status, hot/cold/gas balances, pending work,
 * reconciliation) for the admin Blockchain console.
 */
class ChainHealthService
{
    /** Probe every RPC endpoint and update its health columns. */
    public function checkAll(): int
    {
        $checked = 0;
        foreach (RpcEndpoint::where('is_active', true)->get() as $rpc) {
            $this->probe($rpc);
            $checked++;
        }

        return $checked;
    }

    private function probe(RpcEndpoint $rpc): void
    {
        // Simulated probe: mostly up, occasional degraded, block height advances.
        $roll = random_int(1, 100);
        $status = $roll > 96 ? 'down' : ($roll > 88 ? 'degraded' : 'up');
        $latency = $status === 'down' ? null : random_int(35, $status === 'degraded' ? 900 : 220);

        $base = (int) ($rpc->last_block ?? $this->genesis($rpc->chain->key->value ?? ''));
        $lastBlock = $status === 'down' ? $rpc->last_block : $base + random_int(1, 30);

        $rpc->update([
            'status' => $status,
            'latency_ms' => $latency,
            'last_block' => $lastBlock,
            'last_checked_at' => now(),
        ]);
    }

    private function genesis(string $chainKey): int
    {
        return match ($chainKey) {
            'ethereum' => 21_000_000,
            'bsc' => 44_000_000,
            'tron' => 66_000_000,
            default => 1_000_000,
        };
    }

    /** Per-chain operational summary for the admin console. */
    public function summary(): Collection
    {
        return Chain::where('is_active', true)->get()->map(function (Chain $chain) {
            $rpcs = RpcEndpoint::where('chain_id', $chain->id)->orderBy('priority')->get();
            $native = Asset::where('chain_id', $chain->id)->whereNull('contract_address')->first();
            $gas = GasWallet::where('chain_id', $chain->id)->first();

            return [
                'chain' => $chain,
                'rpcs' => $rpcs,
                'rpc_up' => $rpcs->where('status', 'up')->count(),
                'rpc_total' => $rpcs->count(),
                'tip' => $rpcs->max('last_block'),
                'hot' => $native ? $this->treasuryBalance(LedgerAccountType::TreasuryHot, $native) : null,
                'cold' => $native ? $this->treasuryBalance(LedgerAccountType::TreasuryCold, $native) : null,
                'gas' => $gas,
                'gas_low' => $gas?->isLow() ?? false,
                'pending_deposits' => Deposit::where('required_confirmations', '>', 0)
                    ->whereHas('asset', fn ($q) => $q->where('chain_id', $chain->id))
                    ->whereIn('status', ['detected', 'confirming'])->count(),
                'pending_sweeps' => Sweep::whereHas('asset', fn ($q) => $q->where('chain_id', $chain->id))
                    ->whereIn('status', ['pending', 'gassing', 'signing', 'broadcast'])->count(),
                'reconciliation' => $native
                    ? ReconciliationRun::where('asset_id', $native->id)->latest()->first()
                    : null,
            ];
        });
    }

    private function treasuryBalance(LedgerAccountType $type, Asset $asset): Money
    {
        $credit = LedgerLine::whereHas('account', fn ($q) => $q->where('type', $type->value)->where('asset_id', $asset->id))
            ->where('side', 'credit')->sum('amount');
        $debit = LedgerLine::whereHas('account', fn ($q) => $q->where('type', $type->value)->where('asset_id', $asset->id))
            ->where('side', 'debit')->sum('amount');

        // Debit-normal treasury account: balance = debit - credit.
        $bal = BigInteger::of((string) $debit)->minus((string) $credit);

        return Money::ofBase($bal, $asset->decimals, $asset->symbol);
    }
}
