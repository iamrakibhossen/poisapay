<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Tron\TronSweepAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Enums\OnchainTxStatus;
use App\Enums\SweepStatus;
use App\Models\Asset;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use App\Models\Sweep;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * REAL EVM ERC-20 sweep — moves a deposit address's on-chain token balance into the
 * hot wallet, signing with the deposit address's own derived key. Broadcasts only; the
 * ledger (treasury:pending → treasury:hot) is settled by {@see SettleEvmSweepsAction}
 * after confirmation, so the books follow the chain. The EVM sibling of
 * {@see TronSweepAction}.
 *
 * On-chain balances carry the token's on-chain precision (e.g. BSC 18-dp), so the
 * recorded Sweep amount is scaled DOWN to the ledger's precision. Opt-in, default OFF;
 * gas is ensured via {@see EvmGasSponsor} when its flag is on (otherwise the broadcast
 * proceeds and fails on gas as before).
 */
class EvmSweepAction
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
        private readonly GasEstimationService $gas,
        private readonly EvmGasSponsor $sponsor,
    ) {}

    public function execute(DepositAddress $address, Asset $asset): ?Sweep
    {
        if (! feature('onchain_sweep_enabled', false) || $asset->contract_address === null) {
            return null;
        }

        $chain = $asset->chain;
        if ($chain === null || ! $chain->is_evm) {
            return null;
        }
        $chainType = $chain->key;

        $nonceContext = "sweep:onchain:{$address->id}:{$asset->id}";
        $existing = Sweep::where('nonce_context', $nonceContext)->first();
        if ($existing !== null && in_array($existing->status, [SweepStatus::Broadcast, SweepStatus::Swept], true)) {
            return $existing;
        }

        $onchainRaw = Evm::hexToInt($this->chain->call($chainType, $asset->contract_address, Abi::erc20BalanceOf($address->address)));
        if (bccomp($onchainRaw, '0') <= 0) {
            return null; // nothing to sweep
        }

        // Ledger works in the asset's precision; scale the on-chain balance down to it.
        $tokenDecimals = (int) config("poisapay.custody.{$chainType->value}.token_decimals", $asset->decimals);
        $ledgerAmount = Evm::scaleDecimals($onchainRaw, $tokenDecimals, $asset->decimals);

        if (feature('gas_sponsoring_enabled', false) && ! $this->sponsor->ensure($address, $asset)->isReady()) {
            return Sweep::updateOrCreate(
                ['nonce_context' => $nonceContext],
                ['deposit_address_id' => $address->id, 'asset_id' => $asset->id, 'amount' => $ledgerAmount, 'gas_cost' => '0', 'status' => SweepStatus::Gassing],
            );
        }

        try {
            $hot = $this->keys->hotWalletAddress($chainType);
            $gasParams = $this->gas->suggest($chainType);
            $tx = new Eip1559Transaction(
                chainId: (int) config("poisapay.custody.{$chainType->value}.chain_id"),
                nonce: (string) $this->chain->getTransactionCount($chainType, $address->address),
                maxPriorityFeePerGas: $gasParams['maxPriorityFeePerGas'],
                maxFeePerGas: $gasParams['maxFeePerGas'],
                gasLimit: $gasParams['gasLimit'],
                to: $asset->contract_address,
                value: '0',
                data: Abi::erc20Transfer($hot, $onchainRaw),
            );
            $signature = $this->signer->sign($tx->signingHash(), $this->keys->derivePrivateKey($chainType, (int) $address->derivation_index));
            $raw = $tx->serialize(substr($signature, 0, 64), substr($signature, 64, 64), (int) hexdec(substr($signature, 128, 2)));
            $txHash = $this->chain->sendRawTransaction($chainType, $raw);
        } catch (Throwable $e) {
            return $this->fail($address, $asset, $ledgerAmount, $nonceContext, $e->getMessage());
        }

        if (! str_starts_with($txHash, '0x')) {
            return $this->fail($address, $asset, $ledgerAmount, $nonceContext, 'Broadcast returned no transaction hash.');
        }

        return DB::transaction(function () use ($address, $asset, $ledgerAmount, $nonceContext, $txHash, $hot): Sweep {
            $onchain = OnchainTx::create([
                'chain_id' => $address->chain_id,
                'tx_hash' => strtolower($txHash),
                'log_index' => 0,
                'from_address' => $address->address,
                'to_address' => $hot,
                'asset_id' => $asset->id,
                'amount' => $ledgerAmount,
                'confirmations' => 0,
                'status' => OnchainTxStatus::Detected,
                'direction' => 'out',
            ]);

            $sweep = Sweep::updateOrCreate(
                ['nonce_context' => $nonceContext],
                [
                    'deposit_address_id' => $address->id,
                    'asset_id' => $asset->id,
                    'amount' => $ledgerAmount,
                    'gas_cost' => '0',
                    'status' => SweepStatus::Broadcast,
                    'onchain_tx_id' => $onchain->id,
                ],
            );

            ActivityLogger::log('sweep.broadcast', $sweep, ['tx' => $txHash, 'amount' => $ledgerAmount]);

            return $sweep;
        });
    }

    private function fail(DepositAddress $address, Asset $asset, string $amount, string $nonceContext, string $reason): Sweep
    {
        $sweep = Sweep::updateOrCreate(
            ['nonce_context' => $nonceContext],
            ['deposit_address_id' => $address->id, 'asset_id' => $asset->id, 'amount' => $amount, 'gas_cost' => '0', 'status' => SweepStatus::Failed],
        );

        ActivityLogger::log('sweep.failed', $sweep, ['reason' => $reason]);

        return $sweep;
    }
}
