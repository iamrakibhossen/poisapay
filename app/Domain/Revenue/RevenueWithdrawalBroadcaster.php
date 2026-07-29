<?php

declare(strict_types=1);

namespace App\Domain\Revenue;

use App\Domain\Chain\Evm\Abi;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\Eip1559Transaction;
use App\Domain\Chain\Evm\Evm;
use App\Domain\Chain\Evm\GasEstimationService;
use App\Domain\Chain\Evm\NonceManager;
use App\Domain\Chain\Tron\Trc20;
use App\Domain\Chain\Tron\TronGridClient;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Domain\Custody\CustodyReadiness;
use App\Domain\Withdrawal\Evm\EvmWithdrawalSigner;
use App\Domain\Withdrawal\Tron\TronWithdrawalSigner;
use App\Enums\ChainType;
use App\Models\RevenueWithdrawal;
use App\Support\Money;
use RuntimeException;

/**
 * Signs + broadcasts an approved revenue withdrawal from the hot wallet and
 * returns the REAL on-chain transaction hash. Mirrors the user-withdrawal signers
 * ({@see EvmWithdrawalSigner},
 * {@see TronWithdrawalSigner}) — it never fabricates a
 * hash. Callers must gate this behind {@see CustodyReadiness}.
 */
class RevenueWithdrawalBroadcaster
{
    public function __construct(
        private readonly BlockchainProvider $evm,
        private readonly TronGridClient $tron,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
        private readonly NonceManager $nonces,
        private readonly GasEstimationService $gas,
    ) {}

    /** @return array{tx_hash: string, gas: ?Money} */
    public function broadcast(RevenueWithdrawal $withdrawal): array
    {
        $withdrawal->loadMissing('asset.chain');
        $asset = $withdrawal->asset;
        if (! $asset->chain) {
            throw new RuntimeException('Revenue withdrawal asset is not on a broadcastable chain.');
        }

        $chain = $asset->chain->key; // Chain::$key is cast to ChainType

        return match (true) {
            $chain === ChainType::Tron => $this->broadcastTron($withdrawal, $chain),
            $chain->isEvm() => $this->broadcastEvm($withdrawal, $chain),
            default => throw new RuntimeException("No broadcaster is wired for {$chain->value}."),
        };
    }

    /** @return array{tx_hash: string, gas: ?Money} */
    private function broadcastEvm(RevenueWithdrawal $w, ChainType $chain): array
    {
        $asset = $w->asset;
        $contract = (string) $asset->contract_address;
        if ($contract === '') {
            throw new RuntimeException('Revenue asset has no token contract to transfer.');
        }

        $hot = $this->keys->hotWalletAddress($chain);
        $privateKey = $this->keys->hotWalletPrivateKey($chain);
        $nonce = $this->nonces->next($chain, $hot);
        $gas = $this->gas->suggest($chain);
        $chainId = (int) config("poisapay.custody.{$chain->value}.chain_id");
        $tokenDecimals = (int) config("poisapay.custody.{$chain->value}.token_decimals", 6);
        $onchainAmount = Evm::scaleDecimals((string) $w->amount, $asset->decimals, $tokenDecimals);

        $tx = new Eip1559Transaction(
            chainId: $chainId,
            nonce: (string) $nonce,
            maxPriorityFeePerGas: $gas['maxPriorityFeePerGas'],
            maxFeePerGas: $gas['maxFeePerGas'],
            gasLimit: $gas['gasLimit'],
            to: $contract,
            value: '0',
            data: Abi::erc20Transfer($w->destination_address, $onchainAmount),
        );

        $signature = $this->signer->sign($tx->signingHash(), $privateKey);
        $raw = $tx->serialize(
            substr($signature, 0, 64),
            substr($signature, 64, 64),
            (int) hexdec(substr($signature, 128, 2)),
        );

        $txHash = $this->evm->sendRawTransaction($chain, $raw);
        if (! str_starts_with($txHash, '0x')) {
            throw new RuntimeException('Broadcast returned no transaction hash.');
        }

        return ['tx_hash' => strtolower($txHash), 'gas' => null];
    }

    /** @return array{tx_hash: string, gas: ?Money} */
    private function broadcastTron(RevenueWithdrawal $w, ChainType $chain): array
    {
        $contract = (string) $w->asset->contract_address;
        if ($contract === '') {
            throw new RuntimeException('Revenue asset has no TRC-20 contract to transfer.');
        }

        $hot = $this->keys->hotWalletAddress($chain);
        $built = $this->tron->triggerSmartContract([
            'owner_address' => $hot,
            'contract_address' => $contract,
            'function_selector' => 'transfer(address,uint256)',
            'parameter' => Trc20::transferCalldata($w->destination_address, (string) $w->amount),
            'fee_limit' => 100_000_000,
            'call_value' => 0,
            'visible' => true,
        ]);

        $tx = $built['transaction'] ?? null;
        $txId = is_array($tx) ? ($tx['txID'] ?? null) : null;
        if (! is_array($tx) || ! $txId) {
            throw new RuntimeException($built['result']['message'] ?? 'Could not build the TRON transfer.');
        }

        $tx['signature'] = [$this->signer->sign($txId, $this->keys->hotWalletPrivateKey($chain))];

        $result = $this->tron->broadcast($tx);
        if (! ($result['result'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Broadcast rejected by the network.');
        }

        return ['tx_hash' => (string) $txId, 'gas' => null];
    }
}
