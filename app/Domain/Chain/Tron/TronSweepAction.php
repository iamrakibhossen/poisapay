<?php

declare(strict_types=1);

namespace App\Domain\Chain\Tron;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Enums\ChainType;
use App\Enums\OnchainTxStatus;
use App\Enums\SweepStatus;
use App\Models\Asset;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use App\Models\Sweep;
use Illuminate\Support\Facades\DB;

/**
 * REAL TRON TRC20 sweep — moves a deposit address's on-chain token balance into the
 * pooled hot wallet, signing with the deposit address's own derived key. It only
 * BROADCASTS here; the ledger (treasury:pending → treasury:hot) is settled by
 * {@see SettleTronSweepsAction} AFTER the sweep tx confirms, so the books can never
 * run ahead of the chain (the exact divergence the simulated sweep produced).
 *
 * Opt-in and default OFF via the `onchain_sweep_enabled` feature flag. Gas: the deposit
 * address must already hold TRX/energy — if the broadcast is rejected the sweep is
 * recorded Failed for operator follow-up (automatic gas-sponsoring is a separate engine).
 * Idempotent per (deposit address, asset) via nonce_context.
 */
class TronSweepAction
{
    public function __construct(
        private readonly TronGridClient $client,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
    ) {}

    public function execute(DepositAddress $address, Asset $asset): ?Sweep
    {
        if (! feature('onchain_sweep_enabled', false) || $asset->contract_address === null) {
            return null;
        }

        $nonceContext = "sweep:onchain:{$address->id}:{$asset->id}";
        $existing = Sweep::where('nonce_context', $nonceContext)->first();
        if ($existing !== null && $existing->status !== SweepStatus::Failed) {
            return $existing; // already in flight or settled — idempotent
        }

        $balance = $this->client->tokenBalance($address->address, $asset->contract_address);
        if (bccomp($balance, '0') <= 0) {
            return null; // nothing to sweep
        }

        $hot = $this->keys->hotWalletAddress(ChainType::Tron);

        $built = $this->client->triggerSmartContract([
            'owner_address' => $address->address,
            'contract_address' => $asset->contract_address,
            'function_selector' => 'transfer(address,uint256)',
            'parameter' => Trc20::transferCalldata($hot, $balance),
            'fee_limit' => 100_000_000,
            'call_value' => 0,
            'visible' => true,
        ]);

        $tx = $built['transaction'] ?? null;
        $txId = is_array($tx) ? ($tx['txID'] ?? null) : null;
        if (! is_array($tx) || $txId === null) {
            return $this->fail($address, $asset, $balance, $nonceContext, (string) ($built['result']['message'] ?? 'Could not build the sweep transaction.'));
        }

        $signature = $this->signer->sign($txId, $this->keys->derivePrivateKey(ChainType::Tron, (int) $address->derivation_index));
        $tx['signature'] = [$signature];

        $result = $this->client->broadcast($tx);
        if (! ($result['result'] ?? false)) {
            return $this->fail($address, $asset, $balance, $nonceContext, (string) ($result['message'] ?? 'Sweep broadcast rejected (insufficient gas/energy?).'));
        }

        return DB::transaction(function () use ($address, $asset, $balance, $nonceContext, $txId, $hot): Sweep {
            $onchain = OnchainTx::create([
                'chain_id' => $address->chain_id,
                'tx_hash' => $txId,
                'log_index' => 0,
                'from_address' => $address->address,
                'to_address' => $hot,
                'asset_id' => $asset->id,
                'amount' => $balance,
                'confirmations' => 0,
                'status' => OnchainTxStatus::Detected,
                'direction' => 'out',
            ]);

            $sweep = Sweep::updateOrCreate(
                ['nonce_context' => $nonceContext],
                [
                    'deposit_address_id' => $address->id,
                    'asset_id' => $asset->id,
                    'amount' => $balance,
                    'gas_cost' => '0',
                    'status' => SweepStatus::Broadcast,
                    'onchain_tx_id' => $onchain->id,
                ],
            );

            ActivityLogger::log('sweep.broadcast', $sweep, ['tx' => $txId, 'amount' => $balance]);

            return $sweep;
        });
    }

    private function fail(DepositAddress $address, Asset $asset, string $amount, string $nonceContext, string $reason): Sweep
    {
        $sweep = Sweep::updateOrCreate(
            ['nonce_context' => $nonceContext],
            [
                'deposit_address_id' => $address->id,
                'asset_id' => $asset->id,
                'amount' => $amount,
                'gas_cost' => '0',
                'status' => SweepStatus::Failed,
            ],
        );

        ActivityLogger::log('sweep.failed', $sweep, ['reason' => $reason]);

        return $sweep;
    }
}
