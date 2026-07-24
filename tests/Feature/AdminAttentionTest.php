<?php

declare(strict_types=1);

use App\Enums\KycTier;
use App\Models\KycProfile;
use App\Models\User;
use App\Support\AdminAttention;

it('exposes all badge keys as integer counts', function () {
    $counts = AdminAttention::counts();

    expect($counts)->toHaveKeys([
        'deposits_pending', 'withdrawals_review', 'kyc_pending', 'compliance_open',
        'card_disputes_open', 'p2p_disputes_open', 'sweeps_pending', 'support_open',
        'webhooks_failed', 'settlements_pending',
    ]);

    foreach ($counts as $value) {
        expect($value)->toBeInt();
    }
});

it('reflects a pending item in its attention count', function () {
    expect(AdminAttention::counts()['kyc_pending'])->toBe(0);

    $user = User::factory()->create();
    KycProfile::create([
        'user_id' => $user->id,
        'requested_tier' => KycTier::Full->value,
        'status' => 'pending',
    ]);

    AdminAttention::flush(); // counts are cached; drop the stale entry

    expect(AdminAttention::counts()['kyc_pending'])->toBe(1);
});
