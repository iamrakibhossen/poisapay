<?php

declare(strict_types=1);

use App\Models\P2pAd;
use Database\Seeders\P2pSeeder;

/*
 * P2pSeeder must attach fiat payment methods to every ad it creates — the
 * marketplace displays them and filters by them, so an ad with an empty
 * payment-method pivot is broken (invisible to the method filter, shows no rails).
 */

beforeEach(function () {
    // USDT asset + the p2p_payment_methods catalog are provided by RegistrySeeder /
    // the P2P migration; ensure the base asset exists for the seeder to fund.
    testAsset('USDT', 6, 'tron');
});

it('attaches at least one payment method to every seeded ad', function () {
    $this->seed(P2pSeeder::class);

    $ads = P2pAd::with('paymentMethods')->get();

    expect($ads)->not->toBeEmpty();
    $ads->each(fn (P2pAd $ad) => expect($ad->paymentMethods)->not->toBeEmpty(
        "Ad {$ad->id} was seeded without any payment method",
    ));
});

it('is idempotent — re-running does not duplicate ads or methods', function () {
    $this->seed(P2pSeeder::class);
    $firstAdCount = P2pAd::count();
    $firstPivot = DB::table('p2p_ad_payment_methods')->count();

    $this->seed(P2pSeeder::class);

    expect(P2pAd::count())->toBe($firstAdCount)
        ->and(DB::table('p2p_ad_payment_methods')->count())->toBe($firstPivot);
});
