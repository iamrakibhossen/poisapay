<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Rewards\GrantRewardAction;
use App\Events\UserRegistered;
use App\Models\Asset;
use App\Models\RewardCampaign;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Grant the welcome bonus once, on registration (§F5.1). Idempotent by key. */
class GrantWelcomeBonus implements ShouldQueue
{
    public function __construct(private readonly GrantRewardAction $grant) {}

    public function handle(UserRegistered $event): void
    {
        $user = User::find($event->userId);
        if (! $user) {
            return;
        }

        // Admin campaign is authoritative; fall back to the legacy config default.
        [$asset, $amount] = $this->resolve(RewardCampaign::live('welcome'));
        if (! $asset || ! $amount->isPositive()) {
            return;
        }

        $this->grant->execute($user, 'welcome', $asset, $amount, "reward:welcome:{$user->id}");
    }

    /** @return array{0: ?Asset, 1: Money} */
    private function resolve(?RewardCampaign $campaign): array
    {
        if ($campaign && ($money = $campaign->fixedMoney())) {
            return [$campaign->asset, $money];
        }

        $bonus = (int) config('poisapay.rewards.welcome_bonus_bdt', 0);
        $bdt = Asset::where('currency_code', 'BDT')->first();

        return [$bdt, $bdt ? $bdt->money((string) $bonus) : Money::zero(2)];
    }
}
