<?php

declare(strict_types=1);

use App\Domain\Compliance\Contracts\ScreeningProvider;
use App\Domain\Compliance\Screening\StubScreeningProvider;
use App\Domain\Exchange\CachingRateProvider;
use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Kyc\Contracts\KycProvider;
use App\Domain\Kyc\Providers\ManualKycProvider;
use App\Domain\Notification\NotificationTransportManager;
use App\Domain\Notification\Transports\LogPushTransport;
use App\Domain\Notification\Transports\LogSmsTransport;
use App\Domain\Ramp\Contracts\PayoutProcessor;
use App\Domain\Ramp\Payout\StubPayoutProcessor;

it('resolves the screening provider to the configured stub', function () {
    $provider = app(ScreeningProvider::class);
    expect($provider)->toBeInstanceOf(StubScreeningProvider::class)
        ->and($provider->name())->toBe('stub');
});

it('resolves the KYC provider to the manual stub', function () {
    $provider = app(KycProvider::class);
    expect($provider)->toBeInstanceOf(ManualKycProvider::class)
        ->and($provider->name())->toBe('manual');
});

it('resolves the payout processor to the configured stub', function () {
    $psp = app(PayoutProcessor::class);
    expect($psp)->toBeInstanceOf(StubPayoutProcessor::class)
        ->and($psp->name())->toBe('stub');
});

it('wraps the rate provider in the caching decorator', function () {
    expect(app(RateProvider::class))->toBeInstanceOf(CachingRateProvider::class);
});

it('resolves notification transports per channel from config', function () {
    $manager = app(NotificationTransportManager::class);
    expect($manager->for('sms'))->toBeInstanceOf(LogSmsTransport::class)
        ->and($manager->for('push'))->toBeInstanceOf(LogPushTransport::class);
});

it('lets a driver be swapped via config with no call-site change', function () {
    config()->set('providers.payout.driver', 'stub');
    app()->forgetInstance(PayoutProcessor::class);

    expect(app(PayoutProcessor::class))->toBeInstanceOf(StubPayoutProcessor::class);
});
