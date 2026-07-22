<?php

declare(strict_types=1);

use App\Domain\Webhook\WebhookService;
use App\Jobs\DispatchWebhookJob;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('computes a stable HMAC signature', function () {
    $sig = WebhookService::sign('{"a":1}', 'secret');

    expect($sig)->toBe('sha256='.hash_hmac('sha256', '{"a":1}', 'secret'));
});

it('queues a delivery for each subscribed endpoint', function () {
    Queue::fake();
    $merchant = User::factory()->create();
    WebhookEndpoint::create([
        'user_id' => $merchant->id, 'url' => 'https://example.test/hook',
        'secret' => 'shh', 'events' => ['invoice.paid'], 'is_active' => true,
    ]);
    // An endpoint not subscribed to this event.
    WebhookEndpoint::create([
        'user_id' => $merchant->id, 'url' => 'https://example.test/other',
        'secret' => 'shh', 'events' => ['deposit.confirmed'], 'is_active' => true,
    ]);

    app(WebhookService::class)->dispatch($merchant->id, 'invoice.paid', ['x' => 1]);

    expect(WebhookDelivery::count())->toBe(1);
    Queue::assertPushed(DispatchWebhookJob::class, 1);
});

it('signs and delivers a webhook, marking it delivered', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    $merchant = User::factory()->create();
    $endpoint = WebhookEndpoint::create([
        'user_id' => $merchant->id, 'url' => 'https://example.test/hook',
        'secret' => 'topsecret', 'events' => ['invoice.paid'], 'is_active' => true,
    ]);
    $delivery = WebhookDelivery::create([
        'endpoint_id' => $endpoint->id, 'event' => 'invoice.paid',
        'payload' => ['x' => 1], 'attempt' => 0, 'status' => 'pending',
    ]);

    (new DispatchWebhookJob($delivery->id))->handle();

    Http::assertSent(fn ($request) => $request->hasHeader('X-PoisaPay-Signature')
        && str_starts_with($request->header('X-PoisaPay-Signature')[0], 'sha256='));
    expect($delivery->fresh()->status)->toBe('delivered');
});
