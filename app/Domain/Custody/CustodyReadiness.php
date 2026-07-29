<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Tron\TronGridClient;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Exceptions\CustodyNotReadyException;
use App\Enums\ChainType;
use App\Models\GasWallet;
use Throwable;

/**
 * Pre-flight readiness gate for on-chain withdrawals. A withdrawal is only
 * processed when EVERY prerequisite is met — live mode, consistent flags, a
 * usable signer/hot wallet, a funded (non-critical) gas wallet, and a reachable
 * RPC. If anything is missing it refuses (rather than fabricating a result), so
 * flipping to live custody without real infrastructure fails safe.
 */
class CustodyReadiness
{
    public function __construct(
        private readonly SignerKeyProvider $keys,
        private readonly BlockchainProvider $evm,
        private readonly TronGridClient $tron,
    ) {}

    /**
     * @return list<string> problems; empty means ready.
     */
    public function check(ChainType $chain): array
    {
        $problems = [];

        try {
            CustodyMode::assertConsistent();
        } catch (Throwable $e) {
            $problems[] = $e->getMessage();
        }

        if (! CustodyMode::isLive()) {
            $problems[] = 'Custody is in simulated mode — set POISAPAY_CUSTODY_LIVE=true for live withdrawals.';

            return $problems; // signers/RPC are meaningless while simulated
        }

        try {
            $this->keys->hotWalletAddress($chain);
        } catch (Throwable $e) {
            $problems[] = "{$chain->label()} signer/hot wallet unavailable: ".$e->getMessage();
        }

        $gas = GasWallet::whereHas('chain', fn ($q) => $q->where('key', $chain->value))->first();
        if (! $gas) {
            $problems[] = "No gas wallet is configured for {$chain->label()}.";
        } elseif (! $gas->canPayGas()) {
            $problems[] = "{$chain->label()} gas wallet is critically low — it cannot pay network gas.";
        }

        try {
            $chain->isEvm() ? $this->evm->blockNumber($chain) : $this->tron->latestBlock();
        } catch (Throwable $e) {
            $problems[] = "{$chain->label()} RPC is unreachable: ".$e->getMessage();
        }

        return $problems;
    }

    public function isReady(ChainType $chain): bool
    {
        return $this->check($chain) === [];
    }

    public function assertReady(ChainType $chain): void
    {
        $problems = $this->check($chain);
        if ($problems !== []) {
            throw new CustodyNotReadyException('Custody is not ready to broadcast: '.implode('; ', $problems));
        }
    }
}
