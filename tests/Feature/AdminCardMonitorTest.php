<?php

declare(strict_types=1);

use App\Jobs\ProcessCardWebhookJob;
use App\Models\Admin;
use App\Models\CardProvider;
use App\Models\CardProviderLog;
use App\Models\CardWebhook;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    $this->operator = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->operator->syncRoles(['super-admin']);

    CardProvider::firstOrCreate(['slug' => 'mock-issuer'], [
        'name' => 'Mock Issuer', 'driver' => 'mock', 'network' => 'visa', 'is_active' => true,
    ]);
});

it('renders the provider logs page', function () {
    CardProviderLog::create([
        'driver' => 'mock', 'direction' => 'outbound', 'operation' => 'createVirtualCard', 'success' => true,
    ]);

    actingAs($this->operator, 'admin')->get(route('admin.card-logs'))
        ->assertOk()->assertSee('Card Provider Logs')->assertSee('createVirtualCard');
});

it('renders the webhooks page', function () {
    CardWebhook::create([
        'driver' => 'mock', 'provider_event_id' => 'evt_1', 'event_type' => 'transaction.cleared',
        'signature_valid' => true, 'status' => 'processed',
    ]);

    actingAs($this->operator, 'admin')->get(route('admin.card-webhooks'))
        ->assertOk()->assertSee('transaction.cleared');
});

it('re-queues a failed webhook on retry', function () {
    Queue::fake();
    $webhook = CardWebhook::create([
        'driver' => 'mock', 'provider_event_id' => 'evt_fail', 'event_type' => 'transaction.cleared',
        'signature_valid' => true, 'status' => 'failed', 'error' => 'boom',
    ]);

    actingAs($this->operator, 'admin')->post(route('admin.card-webhooks.retry', $webhook->id))
        ->assertRedirect();

    Queue::assertPushed(ProcessCardWebhookJob::class);
    expect($webhook->refresh()->status)->toBe('pending');
});

it('renders the provider health page', function () {
    Http::fake(['*' => Http::response([], 200)]); // marqeta ping

    actingAs($this->operator, 'admin')->get(route('admin.card-health'))
        ->assertOk()->assertSee('Card Provider Health')->assertSee('Mock Issuer');
});

it('forbids an operator without card permission', function () {
    $support = Admin::create([
        'name' => 'Sup', 'email' => 'sup@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $support->syncRoles(['support']); // no view-cards

    actingAs($support, 'admin')->get(route('admin.card-logs'))->assertForbidden();
});
