<?php

declare(strict_types=1);

namespace App\Domain\Chain\Tron;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Domain\Ledger\AccountResolver;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Enums\OnchainTxStatus;
use App\Models\Asset;
use App\Models\CustodyXpub;
use App\Models\OnchainTx;
use App\Models\TreasuryMove;
use Illuminate\Support\Facades\DB;

/**
 * Moves excess TRON USDT from the hot wallet to cold storage on-chain when the hot
 * treasury is above its high-watermark. Mirrors the sweep engine: it only BROADCASTS
 * here (signed with the hot key, to the cold-watch xpub's address); the ledger move
 * treasury:hot → treasury:cold is posted by {@see SettleTronHotColdMovesAction} ONLY
 * after confirmation, so the books follow the chain.
 *
 * Opt-in and default OFF via `hot_cold_move_enabled`. Excess = treasury:hot −
 * high-watermark (moves hot down to the high-watermark). Idempotent: it will not
 * broadcast a second move while one is still in flight for the asset.
 */
class TronHotColdMoveAction
{
    public function __construct(
        private readonly TronGridClient $client,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
        private readonly AccountResolver $accounts,
        private readonly AddressDeriver $deriver,
    ) {}

    public function execute(Asset $asset): ?TreasuryMove
    {
        if (! feature('hot_cold_move_enabled', false) || $asset->contract_address === null) {
            return null;
        }

        $chain = $asset->chain;
        if ($chain === null || $chain->key !== ChainType::Tron) {
            return null;
        }

        $high = (string) getSetting("custody.watermark.high.{$asset->symbol}", '0');
        if (bccomp($high, '0') <= 0) {
            return null; // no target configured
        }

        $hot = ltrim($this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id)->fresh('balance')->money()->baseString(), '-');
        $excess = bcsub($hot, $high);
        if (bccomp($excess, '0') <= 0) {
            return null; // hot is at/below target
        }

        // Idempotency: don't broadcast a second move while one is in flight.
        if (TreasuryMove::where('asset_id', $asset->id)->where('status', 'broadcast')->exists()) {
            return null;
        }

        $coldXpub = CustodyXpub::where('chain_id', $chain->id)->where('purpose', 'cold-watch')->where('is_active', true)->first();
        if ($coldXpub === null) {
            return null; // no cold destination configured
        }
        $coldAddress = $this->deriver->derive(ChainType::Tron, (string) $coldXpub->xpub, 0);
        $hotAddress = $this->keys->hotWalletAddress(ChainType::Tron);

        $built = $this->client->triggerSmartContract([
            'owner_address' => $hotAddress,
            'contract_address' => $asset->contract_address,
            'function_selector' => 'transfer(address,uint256)',
            'parameter' => Trc20::transferCalldata($coldAddress, $excess),
            'fee_limit' => 100_000_000,
            'call_value' => 0,
            'visible' => true,
        ]);

        $tx = $built['transaction'] ?? null;
        $txId = is_array($tx) ? ($tx['txID'] ?? null) : null;
        if (! is_array($tx) || $txId === null) {
            ActivityLogger::log('treasury.move.failed', null, ['asset' => $asset->symbol, 'reason' => $built['result']['message'] ?? 'build failed']);

            return null;
        }

        $tx['signature'] = [$this->signer->sign($txId, $this->keys->hotWalletPrivateKey(ChainType::Tron))];

        $result = $this->client->broadcast($tx);
        if (! ($result['result'] ?? false)) {
            ActivityLogger::log('treasury.move.failed', null, ['asset' => $asset->symbol, 'reason' => $result['message'] ?? 'broadcast rejected']);

            return null;
        }

        return DB::transaction(function () use ($asset, $chain, $excess, $txId, $hotAddress, $coldAddress): TreasuryMove {
            $onchain = OnchainTx::create([
                'chain_id' => $chain->id,
                'tx_hash' => $txId,
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
                'nonce_context' => "move:hotcold:{$asset->id}:{$txId}",
                'onchain_tx_id' => $onchain->id,
            ]);

            ActivityLogger::log('treasury.move.broadcast', $move, ['tx' => $txId, 'amount' => $excess, 'to' => $coldAddress]);

            return $move;
        });
    }
}
