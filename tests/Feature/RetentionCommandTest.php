<?php
declare(strict_types=1);
use App\Models\CardWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\User;

/** Create a row then backdate its timestamps (created_at is guarded). */
function backdate($model, $when) {
    $model->forceFill(['created_at' => $when, 'updated_at' => $when])->saveQuietly();
    return $model;
}

it('prunes settled webhook records past the window but keeps pending ones', function () {
    $old = now()->subDays(120);
    $ep = WebhookEndpoint::create(['user_id' => User::factory()->create()->id, 'url' => 'https://x.test/hook', 'secret' => 's', 'events' => ['deposit.confirmed'], 'is_active' => true]);

    // Old + terminal → pruned.
    backdate(WebhookDelivery::create(['endpoint_id' => $ep->id, 'event' => 'e', 'payload' => [], 'attempt' => 1, 'status' => 'delivered']), $old);
    backdate(CardWebhook::create(['driver' => 'stripe', 'provider_event_id' => 'evt_old', 'event_type' => 'transaction.cleared', 'status' => 'processed']), $old);
    // Old but PENDING → kept.
    backdate(WebhookDelivery::create(['endpoint_id' => $ep->id, 'event' => 'e', 'payload' => [], 'attempt' => 0, 'status' => 'pending']), $old);
    backdate(CardWebhook::create(['driver' => 'stripe', 'provider_event_id' => 'evt_pending', 'event_type' => 'x', 'status' => 'pending']), $old);
    // Recent + terminal → kept.
    CardWebhook::create(['driver' => 'stripe', 'provider_event_id' => 'evt_recent', 'event_type' => 'x', 'status' => 'processed']);

    $this->artisan('poisapay:retention')->assertSuccessful();

    expect(WebhookDelivery::where('status', 'delivered')->count())->toBe(0)
        ->and(WebhookDelivery::where('status', 'pending')->count())->toBe(1)
        ->and(CardWebhook::where('provider_event_id', 'evt_old')->exists())->toBeFalse()
        ->and(CardWebhook::where('provider_event_id', 'evt_pending')->exists())->toBeTrue()
        ->and(CardWebhook::where('provider_event_id', 'evt_recent')->exists())->toBeTrue();
});
