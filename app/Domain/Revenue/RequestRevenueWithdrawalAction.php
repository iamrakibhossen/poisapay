<?php

declare(strict_types=1);

namespace App\Domain\Revenue;

use App\Domain\Audit\ActivityLogger;
use App\Enums\RevenueWithdrawalStatus;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\RevenueWithdrawal;
use App\Support\Money;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Request a withdrawal from the company Revenue Wallet (§Finance). Validates the
 * amount against the ledger-derived balance and records a Pending withdrawal for
 * a second operator to approve. No funds move until approval. The requesting
 * operator's password/2FA is verified at the UI boundary before this runs.
 */
class RequestRevenueWithdrawalAction
{
    public function __construct(private readonly RevenueService $revenue) {}

    public function execute(Admin $operator, Asset $asset, Money $amount, string $network, string $destinationAddress, ?string $note = null): RevenueWithdrawal
    {
        if (! $amount->isPositive()) {
            throw new RuntimeException('Amount must be greater than zero.');
        }
        if (trim($destinationAddress) === '') {
            throw new RuntimeException('A destination address is required.');
        }

        $available = $this->revenue->balance($asset);
        if ($amount->isGreaterThanOrEqual($available) && ! $amount->equals($available)) {
            throw new RuntimeException("You can withdraw at most {$available->format()} from the revenue wallet.");
        }

        $withdrawal = RevenueWithdrawal::create([
            'asset_id' => $asset->id,
            'amount' => $amount->baseString(),
            'network' => $network ?: $asset->chain?->name,
            'destination_address' => trim($destinationAddress),
            'note' => $note,
            'status' => RevenueWithdrawalStatus::Pending,
            'idempotency_key' => 'revwd:'.Str::uuid()->toString(),
            'created_by' => $operator->id,
        ]);

        ActivityLogger::log('revenue.withdrawal.requested', $withdrawal, [
            'amount' => $amount->baseString(),
            'asset' => $asset->symbol,
            'destination' => $destinationAddress,
        ], actor: $operator);

        notifyAdmins(
            'Revenue withdrawal awaiting approval',
            "{$operator->name} requested a {$amount->format()} revenue withdrawal.",
            route('admin.revenue-withdrawals'),
            'finance',
        );

        return $withdrawal;
    }
}
