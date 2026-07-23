<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Gas\Contracts\GasSponsor;
use App\Domain\Chain\Gas\SponsorResult;
use App\Domain\Chain\Tron\TronGasSponsor;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Models\Asset;
use App\Models\DepositAddress;
use App\Models\GasSponsorship;
use Throwable;

/**
 * EVM gas sponsor: tops a deposit address up with native coin (ETH/BNB…) from the hot
 * wallet so it can pay gas for its own ERC-20 sweep. The EVM sibling of
 * {@see TronGasSponsor}; same idempotency / bounded-retry /
 * dead-letter / audit model via gas_sponsorships. Opt-in, default OFF.
 */
class EvmGasSponsor implements GasSponsor
{
    public function __construct(
        private readonly BlockchainProvider $chain,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
        private readonly NonceManager $nonces,
        private readonly GasEstimationService $gas,
    ) {}

    public function ensure(DepositAddress $address, Asset $asset): SponsorResult
    {
        if (! feature('gas_sponsoring_enabled', false)) {
            return SponsorResult::skipped('flag off');
        }

        $chain = $asset->chain;
        if ($chain === null || ! $chain->is_evm) {
            return SponsorResult::skipped('not an EVM chain');
        }
        $chainType = $chain->key;

        $budget = (string) config("poisapay.custody.{$chainType->value}.sweep_gas_budget_wei", '20000000000000000'); // 0.02
        $maxAttempts = (int) config('poisapay.custody.gas_max_attempts', 3);

        $balance = $this->chain->getBalance($chainType, $address->address);

        $sponsorship = GasSponsorship::firstOrCreate(
            ['chain_id' => $address->chain_id, 'target_address' => $address->address, 'purpose' => 'sweep'],
            ['status' => 'pending', 'amount_sun' => '0', 'attempts' => 0],
        );

        if (bccomp($balance, $budget) >= 0) {
            if ($sponsorship->status !== 'ready') {
                $sponsorship->update(['status' => 'ready']);
            }

            return SponsorResult::ready();
        }

        if ($sponsorship->status === 'failed') {
            return SponsorResult::failed('previously dead-lettered');
        }

        if ($sponsorship->attempts >= $maxAttempts) {
            $sponsorship->update(['status' => 'failed', 'last_error' => 'max attempts exhausted']);
            notifyAdmins(
                'Gas sponsorship dead-lettered',
                "Could not fund {$address->address} on {$chainType->value} for sweeping after {$maxAttempts} attempts. Top up the hot wallet or investigate.",
                null,
                'security',
            );

            return SponsorResult::failed('max attempts exhausted');
        }

        try {
            $topUp = bcsub($budget, $balance);
            $hot = $this->keys->hotWalletAddress($chainType);
            $gasParams = $this->gas->suggest($chainType);
            $tx = new Eip1559Transaction(
                chainId: (int) config("poisapay.custody.{$chainType->value}.chain_id"),
                nonce: (string) $this->nonces->next($chainType, $hot),
                maxPriorityFeePerGas: $gasParams['maxPriorityFeePerGas'],
                maxFeePerGas: $gasParams['maxFeePerGas'],
                gasLimit: '21000',
                to: $address->address,
                value: $topUp,
                data: '0x',
            );
            $signature = $this->signer->sign($tx->signingHash(), $this->keys->hotWalletPrivateKey($chainType));
            $raw = $tx->serialize(substr($signature, 0, 64), substr($signature, 64, 64), (int) hexdec(substr($signature, 128, 2)));
            $txHash = $this->chain->sendRawTransaction($chainType, $raw);
        } catch (Throwable $e) {
            $sponsorship->update(['attempts' => $sponsorship->attempts + 1, 'last_error' => $e->getMessage()]);

            return SponsorResult::pending("retry: {$e->getMessage()}");
        }

        $sponsorship->update([
            'status' => 'funded',
            'amount_sun' => $topUp,
            'tx_hash' => strtolower($txHash),
            'attempts' => $sponsorship->attempts + 1,
            'funded_at' => now(),
            'last_error' => null,
        ]);

        ActivityLogger::log('gas.sponsored', $sponsorship, ['tx' => $txHash, 'amount_wei' => $topUp, 'target' => $address->address]);

        return SponsorResult::pending('funded, awaiting confirmation');
    }
}
