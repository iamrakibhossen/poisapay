<?php

declare(strict_types=1);

namespace App\Domain\Chain\Tron;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Chain\Gas\Contracts\GasSponsor;
use App\Domain\Chain\Gas\SponsorResult;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Enums\ChainType;
use App\Models\Asset;
use App\Models\DepositAddress;
use App\Models\GasSponsorship;

/**
 * TRON gas sponsor. A TRC20 transfer needs Energy; the simplest, most reliable way to
 * let a deposit address pay for its own sweep is to ensure it holds enough TRX to burn
 * for that Energy. This tops the address up from the hot wallet to a configured budget,
 * once, tracked in gas_sponsorships for idempotency / bounded retry / dead-lettering.
 *
 * Opt-in and default OFF via the `gas_sponsoring_enabled` flag. Real money movement
 * (native TRX out of the hot wallet) — every send is audited and bounded.
 */
class TronGasSponsor implements GasSponsor
{
    public function __construct(
        private readonly TronGridClient $client,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
    ) {}

    public function ensure(DepositAddress $address, Asset $asset): SponsorResult
    {
        if (! feature('gas_sponsoring_enabled', false)) {
            return SponsorResult::skipped('flag off');
        }

        $budget = (string) config('poisapay.custody.tron.sweep_gas_budget_sun', '30000000'); // ~30 TRX
        $maxAttempts = (int) config('poisapay.custody.tron.gas_max_attempts', 3);

        $balance = $this->client->accountTrxBalance($address->address);

        $sponsorship = GasSponsorship::firstOrCreate(
            ['chain_id' => $address->chain_id, 'target_address' => $address->address, 'purpose' => 'sweep'],
            ['status' => 'pending', 'amount_sun' => '0', 'attempts' => 0],
        );

        // Already funded enough — good to go.
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
                "Could not fund {$address->address} on TRON for sweeping after {$maxAttempts} attempts. Top up the hot wallet's TRX or investigate.",
                null,
                'security',
            );

            return SponsorResult::failed('max attempts exhausted');
        }

        // Send the top-up (budget - current balance) from the hot wallet.
        $topUp = bcsub($budget, $balance);
        $gasAddress = $this->keys->hotWalletAddress(ChainType::Tron);

        $built = $this->client->createTrxTransfer($gasAddress, $address->address, $topUp);
        $txId = $built['txID'] ?? null;
        if (! is_string($txId) || $txId === '') {
            return $this->retry($sponsorship, (string) ($built['Error'] ?? 'could not build gas transfer'));
        }

        $built['signature'] = [$this->signer->sign($txId, $this->keys->hotWalletPrivateKey(ChainType::Tron))];

        $result = $this->client->broadcast($built);
        if (! ($result['result'] ?? false)) {
            return $this->retry($sponsorship, (string) ($result['message'] ?? 'gas transfer broadcast rejected'));
        }

        $sponsorship->update([
            'status' => 'funded',
            'amount_sun' => $topUp,
            'tx_hash' => $txId,
            'attempts' => $sponsorship->attempts + 1,
            'funded_at' => now(),
            'last_error' => null,
        ]);

        ActivityLogger::log('gas.sponsored', $sponsorship, ['tx' => $txId, 'amount_sun' => $topUp, 'target' => $address->address]);

        return SponsorResult::pending('funded, awaiting confirmation');
    }

    private function retry(GasSponsorship $sponsorship, string $error): SponsorResult
    {
        $sponsorship->update(['attempts' => $sponsorship->attempts + 1, 'last_error' => $error]);

        return SponsorResult::pending("retry: {$error}");
    }
}
