<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Tron\TronHotColdMoveAction;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Domain\Ledger\AccountResolver;
use App\Enums\LedgerAccountType;
use App\Enums\OnchainTxStatus;
use App\Models\Asset;
use App\Models\CustodyXpub;
use App\Models\OnchainTx;
use App\Models\TreasuryMove;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * EVM sibling of {@see TronHotColdMoveAction}. Moves excess
 * treasury:hot ERC-20 from the hot wallet to cold storage on-chain (EIP-1559, signed
 * with the hot key, nonce from the shared {@see NonceManager}, ledger amount scaled up
 * to the token's precision). Broadcast only; the ledger treasury:hot → treasury:cold
 * is posted by {@see SettleEvmHotColdMovesAction} after confirmation.
 *
 * Opt-in, default OFF via `hot_cold_move_enabled`. Excess = treasury:hot − high-watermark.
 * Idempotent (one in-flight move per asset).
 */
class EvmHotColdMoveAction
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
        private readonly NonceManager $nonces,
        private readonly GasEstimationService $gas,
        private readonly AccountResolver $accounts,
        private readonly AddressDeriver $deriver,
    ) {}

    public function execute(Asset $asset): ?TreasuryMove
    {
        if (! feature('hot_cold_move_enabled', false) || $asset->contract_address === null) {
            return null;
        }

        $chain = $asset->chain;
        if ($chain === null || ! $chain->is_evm) {
            return null;
        }
        $chainType = $chain->key;

        $high = (string) getSetting("custody.watermark.high.{$asset->symbol}", '0');
        if (bccomp($high, '0') <= 0) {
            return null;
        }

        $hot = ltrim($this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id)->fresh('balance')->money()->baseString(), '-');
        $excess = bcsub($hot, $high);
        if (bccomp($excess, '0') <= 0) {
            return null;
        }

        if (TreasuryMove::where('asset_id', $asset->id)->where('status', 'broadcast')->exists()) {
            return null;
        }

        $coldXpub = CustodyXpub::where('chain_id', $chain->id)->where('purpose', 'cold-watch')->where('is_active', true)->first();
        if ($coldXpub === null) {
            return null;
        }
        $coldAddress = $this->deriver->derive($chainType, (string) $coldXpub->xpub, 0);
        $hotAddress = $this->keys->hotWalletAddress($chainType);

        $tokenDecimals = (int) config("poisapay.custody.{$chainType->value}.token_decimals", $asset->decimals);
        $onchainAmount = Evm::scaleDecimals($excess, $asset->decimals, $tokenDecimals);

        try {
            $nonce = $this->nonces->next($chainType, $hotAddress);
            $gasParams = $this->gas->suggest($chainType);
            $tx = new Eip1559Transaction(
                chainId: (int) config("poisapay.custody.{$chainType->value}.chain_id"),
                nonce: (string) $nonce,
                maxPriorityFeePerGas: $gasParams['maxPriorityFeePerGas'],
                maxFeePerGas: $gasParams['maxFeePerGas'],
                gasLimit: $gasParams['gasLimit'],
                to: (string) $asset->contract_address,
                value: '0',
                data: Abi::erc20Transfer($coldAddress, $onchainAmount),
            );
            $signature = $this->signer->sign($tx->signingHash(), $this->keys->hotWalletPrivateKey($chainType));
            $raw = $tx->serialize(substr($signature, 0, 64), substr($signature, 64, 64), (int) hexdec(substr($signature, 128, 2)));
            $txHash = $this->chain->sendRawTransaction($chainType, $raw);
        } catch (Throwable $e) {
            ActivityLogger::log('treasury.move.failed', null, ['asset' => $asset->symbol, 'reason' => $e->getMessage()]);

            return null;
        }

        if (! str_starts_with($txHash, '0x')) {
            ActivityLogger::log('treasury.move.failed', null, ['asset' => $asset->symbol, 'reason' => 'no tx hash']);

            return null;
        }

        return DB::transaction(function () use ($asset, $chain, $excess, $txHash, $hotAddress, $coldAddress): TreasuryMove {
            $onchain = OnchainTx::create([
                'chain_id' => $chain->id,
                'tx_hash' => strtolower($txHash),
                'log_index' => 0,
                'from_address' => $hotAddress,
                'to_address' => $coldAddress,
                'asset_id' => $asset->id,
                'amount' => $excess,
                'confirmations' => 0,
                'status' => OnchainTxStatus::Detected,
                'direction' => 'out',
            ]);

            $move = TreasuryMove::create([
                'chain_id' => $chain->id,
                'asset_id' => $asset->id,
                'direction' => 'hot_to_cold',
                'amount' => $excess,
                'status' => 'broadcast',
                'nonce_context' => "move:hotcold:{$asset->id}:".strtolower($txHash),
                'onchain_tx_id' => $onchain->id,
            ]);

            ActivityLogger::log('treasury.move.broadcast', $move, ['tx' => $txHash, 'amount' => $excess, 'to' => $coldAddress]);

            return $move;
        });
    }
}
