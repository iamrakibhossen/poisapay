<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation;

use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\LedgerLine;
use App\Models\ReconciliationRun;
use App\Models\SecurityEvent;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\Log;

/**
 * Solvency reconciliation (TDD §5.4). For each asset, prove:
 *   ledger treasury ≥ ledger liability (Σ user balances).
 * Drift or insolvency is recorded and would page on-call / freeze ops.
 *
 * The on-chain leg (assert on-chain controlled ≈ ledger treasury) is fed by the
 * Blockchain Monitor; here we compute the ledger-internal solvency invariant
 * that must always hold regardless of sweep timing (D1).
 */
class ReconciliationService
{
    public function runForAsset(Asset $asset): ReconciliationRun
    {
        // User balances are pooled per coin while treasury reserves live per
        // chain, so solvency is proven per COIN: Σ treasury across all the coin's
        // networks must cover the coin's pooled user liability.
        $assetIds = $this->coinAssetIds($asset);

        $liability = $this->sumUserAvailable($assetIds);
        $treasury = $this->sumTreasury($assetIds);

        $drift = $treasury->minus($liability);
        $solvent = $treasury->isGreaterThanOrEqualTo($liability);

        $run = ReconciliationRun::create([
            'asset_id' => $asset->id,
            'onchain_controlled' => '0', // populated by the monitor in production
            'ledger_treasury' => (string) $treasury,
            'ledger_liability' => (string) $liability,
            'drift' => (string) $drift,
            'is_solvent' => $solvent,
            'status' => $solvent ? 'ok' : 'insolvent',
        ]);

        if (! $solvent) {
            $this->alertInsolvency($asset, (string) $drift);
        }

        return $run;
    }

    /**
     * Insolvency is a page-on-call event: record a durable critical security signal,
     * fan an operator notification, and log at critical so the alerting pipeline
     * (Slack/Sentry) escalates it. Never throws — alerting must not block the run.
     */
    private function alertInsolvency(Asset $asset, string $drift): void
    {
        try {
            SecurityEvent::create([
                'type' => 'insolvency',
                'severity' => 'critical',
                'risk_score' => 100,
                'metadata' => ['asset_id' => $asset->id, 'symbol' => $asset->symbol, 'drift' => $drift],
            ]);

            Log::critical('Solvency invariant breached', [
                'asset' => $asset->symbol, 'asset_id' => $asset->id, 'drift' => $drift,
            ]);

            notifyAdmins(
                'Insolvency detected',
                "Ledger treasury is below user liability for {$asset->symbol} (drift {$drift}). Freeze withdrawals and investigate immediately.",
                null,
                'security',
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Run reconciliation once per coin (representative asset per currency). */
    public function runAll(): array
    {
        return Asset::where('is_active', true)->get()
            ->groupBy(fn (Asset $a) => $a->currency_id ?? $a->symbol)
            ->map(fn ($group) => $this->runForAsset($group->sortBy('id')->first()))
            ->values()
            ->all();
    }

    /** All active asset ids sharing this asset's coin (its settlement networks). */
    private function coinAssetIds(Asset $asset): array
    {
        if (! $asset->currency_id) {
            return [$asset->id];
        }

        return Asset::where('currency_id', $asset->currency_id)->pluck('id')->all();
    }

    /** @param  array<int, int>  $assetIds */
    private function sumUserAvailable(array $assetIds): BigInteger
    {
        return $this->sumSignedFor($assetIds, [
            LedgerAccountType::UserAvailable,
            LedgerAccountType::UserLocked,
            LedgerAccountType::UserCardHold,
        ]);
    }

    /** @param  array<int, int>  $assetIds */
    private function sumTreasury(array $assetIds): BigInteger
    {
        return $this->sumSignedFor($assetIds, [
            LedgerAccountType::TreasuryHot,
            LedgerAccountType::TreasuryCold,
            LedgerAccountType::TreasuryPending,
        ])->negated(); // treasury accounts are debit-normal; liabilities they back are positive
    }

    /**
     * Σ (credit - debit) across the given account types for a set of assets.
     *
     * @param  array<int, int>  $assetIds
     */
    private function sumSignedFor(array $assetIds, array $types): BigInteger
    {
        $typeValues = array_map(fn (LedgerAccountType $t) => $t->value, $types);

        $credits = LedgerLine::query()
            ->whereIn('ledger_lines.asset_id', $assetIds)
            ->where('side', 'credit')
            ->whereHas('account', fn ($q) => $q->whereIn('type', $typeValues))
            ->sum('amount');

        $debits = LedgerLine::query()
            ->whereIn('ledger_lines.asset_id', $assetIds)
            ->where('side', 'debit')
            ->whereHas('account', fn ($q) => $q->whereIn('type', $typeValues))
            ->sum('amount');

        return BigInteger::of((string) $credits)->minus((string) $debits);
    }
}
