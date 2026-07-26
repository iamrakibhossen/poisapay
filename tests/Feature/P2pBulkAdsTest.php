<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pAdStatus;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
});

function sellerAd(array $overrides = []): P2pAd
{
    return P2pAd::factory()->create(array_merge([
        'user_id' => test()->seller->id,
        'asset_id' => test()->usdt->id,
    ], $overrides));
}

it('bulk-pauses selected active ads', function () {
    $a = sellerAd(['status' => P2pAdStatus::Active]);
    $b = sellerAd(['status' => P2pAdStatus::Active]);
    $c = sellerAd(['status' => P2pAdStatus::Active]); // not selected

    $this->actingAs($this->seller)
        ->post(route('p2p.ads.bulk'), ['action' => 'pause', 'ids' => [$a->id, $b->id]])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($a->fresh()->status)->toBe(P2pAdStatus::Paused)
        ->and($b->fresh()->status)->toBe(P2pAdStatus::Paused)
        ->and($c->fresh()->status)->toBe(P2pAdStatus::Active);
});

it('bulk-resumes and bulk-archives', function () {
    $a = sellerAd(['status' => P2pAdStatus::Paused]);
    $this->actingAs($this->seller)->post(route('p2p.ads.bulk'), ['action' => 'resume', 'ids' => [$a->id]]);
    expect($a->fresh()->status)->toBe(P2pAdStatus::Active);

    $this->actingAs($this->seller)->post(route('p2p.ads.bulk'), ['action' => 'archive', 'ids' => [$a->id]]);
    expect($a->fresh()->status)->toBe(P2pAdStatus::Archived);
});

it('bulk-deletes ads without open orders and skips ones that have them', function () {
    $free = sellerAd();
    $busy = sellerAd();

    // Give $busy an open order.
    $buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    app(CreateOrderAction::class)->execute($buyer, $busy, Money::ofDecimal('100', 6, 'USDT'));

    $this->actingAs($this->seller)
        ->post(route('p2p.ads.bulk'), ['action' => 'delete', 'ids' => [$free->id, $busy->id]])
        ->assertRedirect();

    expect(P2pAd::find($free->id))->toBeNull()          // deleted (soft)
        ->and(P2pAd::find($busy->id))->not->toBeNull();  // skipped — has an open order
});

it('only touches the callers own ads', function () {
    $stranger = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $strangerAd = P2pAd::factory()->create(['user_id' => $stranger->id, 'asset_id' => $this->usdt->id, 'status' => P2pAdStatus::Active]);

    $this->actingAs($this->seller)
        ->post(route('p2p.ads.bulk'), ['action' => 'pause', 'ids' => [$strangerAd->id]])
        ->assertRedirect();

    expect($strangerAd->fresh()->status)->toBe(P2pAdStatus::Active); // untouched
});

it('validates the action', function () {
    $this->actingAs($this->seller)
        ->post(route('p2p.ads.bulk'), ['action' => 'nuke', 'ids' => [sellerAd()->id]])
        ->assertSessionHasErrors('action');
});
