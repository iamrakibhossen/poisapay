<?php

declare(strict_types=1);

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\UserNotification;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Seed a real database notification for a user and return its stored model.
 */
function seedNotification(User $user, array $overrides = [])
{
    $user->notify(new UserNotification(
        title: $overrides['title'] ?? 'Deposit confirmed',
        body: $overrides['body'] ?? 'Your deposit landed.',
        url: $overrides['url'] ?? null,
        category: $overrides['category'] ?? 'money',
        channels: ['database'],
    ));

    return $user->notifications()->latest()->first();
}

it('renders the notifications page via a controller (no Livewire)', function () {
    seedNotification($this->user);

    actingAs($this->user)->get(route('notifications'))
        ->assertOk()
        ->assertSee('Notifications')
        ->assertSee('Deposit confirmed')
        ->assertSee('Your deposit landed.')
        ->assertSee('Preferences');
});

it('marks a single notification read (scoped to the user)', function () {
    $note = seedNotification($this->user);

    actingAs($this->user)->post(route('notifications.read', $note->id))
        ->assertRedirect(route('notifications'));

    expect($note->fresh()->read_at)->not->toBeNull();
});

it('cannot mark another user\'s notification read', function () {
    $other = User::factory()->create();
    $note = seedNotification($other);

    actingAs($this->user)->post(route('notifications.read', $note->id))
        ->assertNotFound();

    expect($note->fresh()->read_at)->toBeNull();
});

it('marks all notifications read', function () {
    seedNotification($this->user);
    seedNotification($this->user, ['title' => 'Second']);

    actingAs($this->user)->post(route('notifications.read-all'))
        ->assertRedirect(route('notifications'))->assertSessionHas('success');

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('persists notification preferences (security channels forced on)', function () {
    $payload = ['prefs' => [
        'security' => ['in_app' => false, 'email' => false, 'sms' => true, 'push' => false],
        'money' => ['in_app' => true, 'email' => false, 'sms' => false, 'push' => true],
        'product' => ['in_app' => false, 'email' => false, 'sms' => false, 'push' => false],
        'marketing' => ['in_app' => false, 'email' => true, 'sms' => false, 'push' => false],
    ]];

    actingAs($this->user)->put(route('notifications.preferences'), $payload)
        ->assertRedirect(route('notifications'))->assertSessionHas('success');

    $security = NotificationPreference::where('user_id', $this->user->id)->where('category', 'security')->first();
    expect($security->in_app)->toBeTrue()   // forced on regardless of payload
        ->and($security->email)->toBeTrue()
        ->and($security->sms)->toBeTrue();

    $money = NotificationPreference::where('user_id', $this->user->id)->where('category', 'money')->first();
    expect($money->in_app)->toBeTrue()->and($money->email)->toBeFalse()->and($money->push)->toBeTrue();
});

it('validates the preferences payload', function () {
    actingAs($this->user)->put(route('notifications.preferences'), [])
        ->assertSessionHasErrors('prefs');
});

it('rejects unknown preference categories', function () {
    actingAs($this->user)->put(route('notifications.preferences'), [
        'prefs' => ['bogus' => ['in_app' => true, 'email' => true, 'sms' => false, 'push' => false]],
    ])->assertSessionHasErrors('prefs');
});

it('requires authentication for the notifications page', function () {
    $this->get(route('notifications'))->assertRedirect(route('login'));
});
