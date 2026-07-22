<?php

declare(strict_types=1);

namespace App\Domain\Rewards;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\RewardGrant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Grant a reward to a user (TDD §F5). The platform funds the reward from its hot
 * treasury into the user's available balance — a real payout, not a phantom
 * credit — via a single balanced ledger entry. Idempotent by the caller's key so
 * a queue retry (or a duplicated referral qualification) never double-grants.
 * Mirrors CreditDepositAction.
 */
class GrantRewardAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(User $user, string $type, Asset $asset, Money $amount, string $idempotencyKey): RewardGrant
    {
        return DB::transaction(function () use ($user, $type, $asset, $amount, $idempotencyKey): RewardGrant {
            // Collapse retries: an existing grant for this key is authoritative.
            $existing = RewardGrant::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $treasury = $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id);
            $available = $this->accounts->forUser($user, LedgerAccountType::UserAvailable, $asset->id);

            $entry = $this->ledger->post(new EntryData(
                type: 'reward.grant',
                idempotencyKey: $idempotencyKey,
                lines: [
                    PostingLine::debit($treasury->id, $asset->id, $amount),
                    PostingLine::credit($available->id, $asset->id, $amount),
                ],
                memo: "Reward {$type}",
                metadata: ['user_id' => $user->id, 'reward_type' => $type],
            ));

            return RewardGrant::create([
                'user_id' => $user->id,
                'type' => $type,
                'asset_id' => $asset->id,
                'amount' => $amount->baseString(),
                'idempotency_key' => $idempotencyKey,
                'entry_id' => $entry->id,
            ]);
        });
    }
}
