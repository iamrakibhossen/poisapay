<?php

declare(strict_types=1);

namespace App\Domain\Deposit;

use App\Domain\Audit\ActivityLogger;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\DepositMethod;
use App\Models\User;
use App\Support\Money;
use RuntimeException;

/**
 * Record a manual (off-chain) deposit request against a configured method
 * (§6.1) — a bank transfer, mobile wallet or manual crypto payment. Creates a
 * pending {@see Deposit} an operator reviews and credits. No money moves here.
 */
class SubmitManualDepositAction
{
    public function execute(User $user, DepositMethod $method, Money $amount, string $reference): Deposit
    {
        $method->loadMissing('asset');

        if (! $method->is_active || ! $method->asset->deposit_enabled || ! $method->asset->is_active) {
            throw new RuntimeException('This deposit method is not available.');
        }
        if (! feature('deposit_enabled')) {
            throw new RuntimeException('Deposits are currently disabled.');
        }
        if ($amount->isLessThan($method->minMoney())) {
            throw new RuntimeException("The minimum for this method is {$method->minMoney()->format()}.");
        }
        if (($max = $method->maxMoney()) && $amount->isGreaterThanOrEqual($max) && ! $amount->equals($max)) {
            throw new RuntimeException("The maximum for this method is {$max->format()}.");
        }
        if (trim($reference) === '') {
            throw new RuntimeException('A payment reference is required.');
        }

        $deposit = Deposit::create([
            'user_id' => $user->id,
            'asset_id' => $method->asset_id,
            'source' => 'manual',
            'deposit_method_id' => $method->id,
            'reference' => trim($reference),
            'amount' => $amount->baseString(),
            'confirmations' => 0,
            'required_confirmations' => 0,
            'status' => DepositStatus::Detected,
        ]);

        ActivityLogger::log('deposit.manual.submitted', $deposit, [
            'method' => $method->name,
            'amount' => $deposit->amount,
        ]);

        notifyAdmins(
            'Manual deposit awaiting review',
            "{$user->name} submitted a {$amount->format()} deposit via {$method->name}.",
            route('admin.deposits'),
            'deposit',
        );

        return $deposit;
    }
}
