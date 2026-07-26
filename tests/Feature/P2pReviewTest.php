<?php

declare(strict_types=1);

use App\Domain\P2p\ConfirmReleaseAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\SubmitReviewAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\P2pOrder;
use App\Models\P2pReview;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');

    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);
});

function completedP2pOrder(): P2pOrder
{
    $order = app(CreateOrderAction::class)->execute(test()->buyer, test()->ad, Money::ofDecimal('100', 6, 'USDT'));
    app(MarkBuyerPaidAction::class)->execute($order->refresh(), test()->buyer);
    app(ConfirmReleaseAction::class)->execute($order->refresh(), test()->seller);

    return $order->refresh();
}

it('records a review and refreshes the seller feedback aggregates', function () {
    $order = completedP2pOrder();

    $review = app(SubmitReviewAction::class)->execute($order, $this->buyer, 5, 'Fast and smooth.');

    expect($review->ratee_id)->toBe($this->seller->id)
        ->and($review->rater_id)->toBe($this->buyer->id)
        ->and($review->is_positive)->toBeTrue();

    $profile = P2pMerchantProfile::where('user_id', $this->seller->id)->first();
    expect((int) $profile->review_count)->toBe(1)
        ->and((int) $profile->positive_count)->toBe(1)
        ->and((float) $profile->rating)->toBe(5.0)
        ->and($profile->positivePercent())->toBe(100.0);
});

it('averages the rating and positive share across reviews', function () {
    // Seller receives a 5-star and a 2-star from two separate completed trades.
    $order1 = completedP2pOrder();
    app(SubmitReviewAction::class)->execute($order1, $this->buyer, 5);

    $order2 = completedP2pOrder();
    app(SubmitReviewAction::class)->execute($order2, $this->buyer, 2);

    $profile = P2pMerchantProfile::where('user_id', $this->seller->id)->first();
    expect((int) $profile->review_count)->toBe(2)
        ->and((int) $profile->positive_count)->toBe(1)          // only the 5-star is positive
        ->and((float) $profile->rating)->toBe(3.5)             // (5 + 2) / 2
        ->and($profile->positivePercent())->toBe(50.0);
});

it('clamps an out-of-range rating into 1–5', function () {
    $order = completedP2pOrder();

    $review = app(SubmitReviewAction::class)->execute($order, $this->buyer, 9);

    expect($review->rating)->toBe(5);
});

it('rejects a second review from the same party on one order', function () {
    $order = completedP2pOrder();
    app(SubmitReviewAction::class)->execute($order, $this->buyer, 5);

    expect(fn () => app(SubmitReviewAction::class)->execute($order, $this->buyer, 4))
        ->toThrow(RuntimeException::class, 'already reviewed');
});

it('lets both parties review the same order independently', function () {
    $order = completedP2pOrder();

    app(SubmitReviewAction::class)->execute($order, $this->buyer, 5);   // buyer rates seller
    app(SubmitReviewAction::class)->execute($order, $this->seller, 4);  // seller rates buyer

    expect(P2pReview::where('order_id', $order->id)->count())->toBe(2)
        ->and(P2pReview::where('ratee_id', $this->buyer->id)->exists())->toBeTrue()
        ->and(P2pReview::where('ratee_id', $this->seller->id)->exists())->toBeTrue();
});

it('blocks a non-party from reviewing', function () {
    $order = completedP2pOrder();
    $stranger = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);

    expect(fn () => app(SubmitReviewAction::class)->execute($order, $stranger, 5))
        ->toThrow(RuntimeException::class, 'party to this trade');
});

it('refuses to review a trade that has not settled', function () {
    $order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));

    expect(fn () => app(SubmitReviewAction::class)->execute($order->refresh(), $this->buyer, 5))
        ->toThrow(RuntimeException::class, 'completed trade');
});

it('submits a review over HTTP and shows it on the merchant profile', function () {
    $order = completedP2pOrder();

    $this->actingAs($this->buyer)
        ->post(route('p2p.order.review', $order), ['rating' => 5, 'comment' => 'Great trader'])
        ->assertRedirect(route('p2p.order', $order))
        ->assertSessionHas('success');

    expect(P2pReview::where('order_id', $order->id)->where('rater_id', $this->buyer->id)->exists())->toBeTrue();

    $this->actingAs($this->buyer)
        ->get(route('p2p.merchant', $this->seller->getKey()))
        ->assertOk()
        ->assertSee('Great trader');
});

it('validates the rating range over HTTP', function () {
    $order = completedP2pOrder();

    $this->actingAs($this->buyer)
        ->post(route('p2p.order.review', $order), ['rating' => 7])
        ->assertSessionHasErrors('rating');
});
