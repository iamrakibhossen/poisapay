<?php

declare(strict_types=1);

namespace App\Domain\Treasury;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\AccountResolver;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\ColdRefillRequest;
use App\Models\CustodyXpub;
use Throwable;

/**
 * Raises a cold → hot refill request when treasury:hot falls below its low-watermark.
 * Cold storage is signed offline (MPC/air-gapped), so this only creates the request +
 * alerts operators — it never moves funds. An operator approves, signs the move offline,
 * records the tx hash, and {@see SettleColdRefillAction} posts the ledger after
 * confirmation. Amount tops hot back up to the high-watermark. Idempotent: one open
 * request per asset. Opt-in, default OFF via `hot_cold_refill_enabled`.
 */
class RequestColdRefillAction
{
    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly AddressDeriver $deriver,
        private readonly SignerKeyProvider $keys,
    ) {}

    public function execute(Asset $asset): ?ColdRefillRequest
    {
        if (! feature('hot_cold_refill_enabled', false) || $asset->contract_address === null) {
            return null;
        }

        $chain = $asset->chain;
        if ($chain === null) {
            return null;
        }

        $low = (string) getSetting("custody.watermark.low.{$asset->symbol}", '0');
        $high = (string) getSetting("custody.watermark.high.{$asset->symbol}", '0');
        if (bccomp($low, '0') <= 0 || bccomp($high, '0') <= 0) {
            return null; // need both a floor to detect and a target to refill to
        }

        $hot = ltrim($this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id)->fresh('balance')->money()->baseString(), '-');
        if (bccomp($hot, $low) >= 0) {
            return null; // not under the floor
        }

        // Idempotency: one open (unsettled) request per asset.
        if (ColdRefillRequest::where('asset_id', $asset->id)->whereIn('status', ['requested', 'approved', 'broadcast'])->exists()) {
            return null;
        }

        $amount = bcsub($high, $hot); // top hot up to the high-watermark

        $coldXpub = CustodyXpub::where('chain_id', $chain->id)->where('purpose', 'cold-watch')->where('is_active', true)->first();
        $coldAddress = $coldXpub !== null ? $this->deriver->derive($chain->key, (string) $coldXpub->xpub, 0) : null;

        $hotAddress = null;
        try {
            $hotAddress = $this->keys->hotWalletAddress($chain->key);
        } catch (Throwable) {
            // hot address not derivable (no seed) — leave null; operator fills it in.
        }

        $request = ColdRefillRequest::create([
            'chain_id' => $chain->id,
            'asset_id' => $asset->id,
            'amount' => $amount,
            'status' => 'requested',
            'cold_address' => $coldAddress,
            'hot_address' => $hotAddress,
        ]);

        notifyAdmins(
            'Cold → hot refill requested',
            "{$asset->symbol} treasury:hot ({$hot}) is below its low-watermark ({$low}). Approve and offline-sign a refill of {$amount} from cold storage to the hot wallet.",
            null,
            'security',
        );

        ActivityLogger::log('cold_refill.requested', $request, ['asset' => $asset->symbol, 'amount' => $amount]);

        return $request;
    }
}
