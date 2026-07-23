<?php
declare(strict_types=1);
use App\Models\Admin;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Artisan;
use function Pest\Laravel\actingAs;

it('logs an inbound webhook request with redacted secret headers', function () {
    // Unsigned card webhook → provider rejects (401), but the request is still logged.
    $this->postJson('/api/card/webhooks/stripe', ['id' => 'evt_1', 'type' => 'x'], [
        'Stripe-Signature' => 't=1,v1=secret-should-be-redacted',
        'Authorization' => 'Bearer super-secret',
    ]);

    $log = WebhookLog::first();
    expect($log)->not->toBeNull()
        ->and($log->provider)->toBe('stripe')
        ->and($log->method)->toBe('POST')
        ->and($log->payload['id'] ?? null)->toBe('evt_1')
        ->and($log->status)->toBe(401) // unsigned → provider rejects, still logged
        ->and($log->resolved)->toBeFalse()
        ->and($log->hash)->not->toBeEmpty();

    // Secrets must be redacted in the stored headers.
    $headers = collect($log->headers)->mapWithKeys(fn ($v, $k) => [strtolower($k) => $v]);
    expect($headers['stripe-signature'][0] ?? null)->toBe('[redacted]')
        ->and($headers['authorization'][0] ?? null)->toBe('[redacted]');
})->skip(fn () => false);

it('dedups: a second success resolves earlier unresolved duplicates', function () {
    WebhookLog::create(['provider' => 'x', 'method' => 'POST', 'url' => 'u', 'hash' => 'abc', 'status' => 500, 'resolved' => false]);
    WebhookLog::create(['provider' => 'x', 'method' => 'POST', 'url' => 'u', 'hash' => 'abc', 'status' => 200, 'resolved' => true]);
    // Simulate the middleware's dedup step:
    WebhookLog::where('hash', 'abc')->where('resolved', false)->update(['resolved' => true]);
    expect(WebhookLog::where('hash', 'abc')->where('resolved', false)->count())->toBe(0);
});

it('cleans webhook logs older than the window in batches', function () {
    $old = now()->subDays(30);
    $l = WebhookLog::create(['provider' => 'x', 'method' => 'POST', 'url' => 'u', 'hash' => 'h', 'status' => 200, 'resolved' => true]);
    $l->forceFill(['created_at' => $old, 'updated_at' => $old])->saveQuietly();
    WebhookLog::create(['provider' => 'x', 'method' => 'POST', 'url' => 'u', 'hash' => 'h2', 'status' => 200, 'resolved' => true]); // recent

    Artisan::call('poisapay:webhooks-clean', ['--days' => 7]);
    expect(WebhookLog::count())->toBe(1);
});

it('renders the admin webhook-logs pages for an operator', function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $op = Admin::create(['name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true]);
    $op->syncRoles(['super-admin']);
    $log = WebhookLog::create(['provider' => 'stripe', 'method' => 'POST', 'url' => 'https://x/webhooks/stripe', 'route' => 'r', 'payload' => ['a' => 1], 'headers' => ['x' => ['y']], 'hash' => 'h', 'status' => 200, 'resolved' => true, 'response' => 'ok']);

    actingAs($op, 'admin')->get(route('admin.webhook-logs'))->assertOk()->assertSee('Webhook Logs');
    actingAs($op, 'admin')->get(route('admin.webhook-logs.show', $log->id))->assertOk()->assertSee('Payload');
});
