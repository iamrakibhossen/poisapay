<?php

declare(strict_types=1);

namespace App\Domain\Chain;

use App\Enums\DepositStatus;
use App\Enums\OnchainTxStatus;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Simulated Blockchain Monitor detection (TDD §6.1 step 4). Stands in for real
 * node scanning: records an onchain_tx + deposit (status=detected, 0 confs) for
 * a user's deposit address. Confirmations are then advanced by the tick job
 * until the deposit is credited — mirroring the real detection→confirm→credit
 * pipeline without a live chain.
 */
class SimulateInboundDepositAction
{
    public function execute(DepositAddress $address, Asset $asset, Money $amount, ?string $txHash = null): Deposit
    {
        return DB::transaction(function () use ($address, $asset, $amount, $txHash): Deposit {
            $txHash ??= '0x'.bin2hex(random_bytes(16)).Str::random(4);

            $tx = OnchainTx::create([
                'chain_id' => $address->chain_id,
                'tx_hash' => $txHash,
                'log_index' => 0,
                'to_address' => $address->address,
                'asset_id' => $asset->id,
                'amount' => $amount->baseString(),
                'confirmations' => 0,
                'status' => OnchainTxStatus::Detected,
                'direction' => 'in',
            ]);

            return Deposit::create([
                'user_id' => $address->user_id,
                'deposit_address_id' => $address->id,
                'asset_id' => $asset->id,
                'onchain_tx_id' => $tx->id,
                'amount' => $amount->baseString(),
                'confirmations' => 0,
                'required_confirmations' => $asset->requiredConfirmations(),
                'status' => DepositStatus::Detected,
            ]);
        });
    }
}
