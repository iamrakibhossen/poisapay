<?php

declare(strict_types=1);

use App\Shop\Tracking\Providers\Ga4Provider;
use App\Shop\Tracking\Providers\GtmProvider;
use App\Shop\Tracking\Providers\MetaProvider;
use App\Shop\Tracking\Providers\TikTokProvider;
use App\Shop\Tracking\TrackingEvent;
use App\Shop\Tracking\TrackingEventType;
use App\Shop\Tracking\TrackingManager;

function manager(): TrackingManager
{
    return new TrackingManager([new MetaProvider, new TikTokProvider, new Ga4Provider, new GtmProvider]);
}

it('renders nothing when no provider is configured', function () {
    $m = manager();

    expect($m->isActive([]))->toBeFalse()
        ->and($m->head([]))->toBe('')
        ->and($m->body([]))->toBe('');
});

it('emits only the enabled provider snippet and the runtime', function () {
    $tracking = [
        'meta' => ['enabled' => true, 'pixel_id' => '123456789012345'],
        'ga4' => ['enabled' => false, 'measurement_id' => 'G-ABCD1234'],
    ];

    $head = manager()->head($tracking);

    expect($head)->toContain("fbq('init',\"123456789012345\")")
        ->and($head)->toContain('window.ppTrack')      // runtime present
        ->and($head)->not->toContain('gtag/js');        // disabled GA4 emits nothing
});

it('ignores an enabled provider with a malformed id', function () {
    $tracking = ['meta' => ['enabled' => true, 'pixel_id' => 'not-a-pixel']];

    expect(manager()->isActive($tracking))->toBeFalse()
        ->and(manager()->head($tracking))->toBe('');
});

it('injects the GTM noscript iframe into the body', function () {
    $tracking = ['gtm' => ['enabled' => true, 'container_id' => 'GTM-ABC123']];

    $body = manager()->body($tracking);

    expect($body)->toContain('googletagmanager.com/ns.html?id=GTM-ABC123')
        ->and($body)->toContain('<noscript>')
        ->and(manager()->body([]))->toBe('');
});

it('serializes initial events into the runtime', function () {
    $tracking = ['meta' => ['enabled' => true, 'pixel_id' => '123456789012345']];
    $events = [
        TrackingEvent::of(TrackingEventType::PageView),
        TrackingEvent::of(TrackingEventType::Purchase, [
            'order_id' => 42, 'value' => 19.99, 'currency' => 'USD', 'product_id' => 7,
        ]),
    ];

    $head = manager()->head($tracking, $events);

    expect($head)->toContain('"type":"page_view"')
        ->and($head)->toContain('"type":"purchase"')
        ->and($head)->toContain('"order_id":42')
        ->and($head)->toContain('"currency":"USD"');
});

it('gates the runtime start on consent when required', function () {
    $tracking = [
        'meta' => ['enabled' => true, 'pixel_id' => '123456789012345'],
        'privacy' => ['consent_required' => true],
    ];

    expect(manager()->head($tracking))->toContain('if(!true){start()}');
    expect(manager()->head(['meta' => $tracking['meta']]))->toContain('if(!false){start()}');
});

it('derives validation rules from provider fields', function () {
    $rules = manager()->validationRules();

    expect($rules)->toHaveKey('tracking.meta.pixel_id')
        ->and($rules['tracking.meta.pixel_id'])->toContain('required_if:tracking.meta.enabled,1,true')
        ->and($rules['tracking.ga4.measurement_id'])->toContain('regex:/^G-[A-Z0-9]{4,12}$/')
        ->and($rules)->toHaveKey('tracking.tiktok.pixel_id')
        ->and($rules)->toHaveKey('tracking.gtm.container_id')
        ->and($rules['tracking.meta.access_token'])->not->toContain('required_if:tracking.meta.enabled,1,true');
});

it('drops blank payload fields from events', function () {
    $e = TrackingEvent::of(TrackingEventType::Lead, ['email' => '', 'country' => 'US', 'value' => null]);

    expect($e->toArray())->toBe(['type' => 'lead', 'data' => ['country' => 'US']]);
});
