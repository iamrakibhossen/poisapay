<?php

declare(strict_types=1);

namespace App\Domain\Compliance\TravelRule;

use App\Domain\Compliance\TravelRule\Contracts\TravelRuleProvider;
use App\Domain\Compliance\TravelRule\DTO\TravelRuleData;
use App\Models\Asset;
use App\Models\TravelRuleRecord;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;

/**
 * Travel Rule orchestration (Wave 5, FATF R.16). Decides when a transfer crosses
 * the reporting threshold, captures the originator/beneficiary record, and hands
 * it to the configured provider. Feature-flagged (default off — enabling it means
 * the deployment is ready to collect/transmit beneficiary data).
 */
class TravelRuleService
{
    public function __construct(private readonly TravelRuleProvider $provider) {}

    public function enabled(): bool
    {
        return feature('security_travel_rule', (bool) config('poisapay.security.flags.travel_rule', false));
    }

    /** True when the rule applies: enabled, a crypto asset, at/above the threshold. */
    public function applies(Asset $asset, Money $amount): bool
    {
        if (! $this->enabled() || $asset->isFiat()) {
            return false;
        }

        $threshold = (string) getSetting('security_travel_rule_threshold', config('poisapay.security.travel_rule_threshold', 1000));

        return bccomp($amount->toDecimal(), $threshold, 8) >= 0;
    }

    /** Capture + submit the Travel Rule message for an outbound (withdrawal) transfer. */
    public function recordForWithdrawal(Withdrawal $withdrawal, User $user, Asset $asset): TravelRuleRecord
    {
        $record = TravelRuleRecord::create([
            'withdrawal_id' => $withdrawal->id,
            'asset_id' => $asset->id,
            'direction' => 'out',
            'amount' => $withdrawal->amount,
            'originator_name' => $user->name,
            'originator_account' => $user->id,
            'beneficiary_address' => $withdrawal->to_address,
            'status' => 'pending',
        ]);

        $result = $this->provider->submit(new TravelRuleData(
            recordId: $record->id,
            direction: 'out',
            asset: $asset->symbol,
            amount: (string) $withdrawal->amount,
            originatorName: (string) $user->name,
            originatorAccount: $user->id,
            beneficiaryAddress: $withdrawal->to_address,
        ));

        $record->update([
            'status' => $result->status,
            'provider' => $this->provider->name(),
            'provider_ref' => $result->providerRef,
        ]);

        return $record;
    }
}
