<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Notifications\OperatorNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    $this->admin = Admin::create([
        'name' => 'Op', 'email' => 'notif@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->admin->syncRoles(['super-admin']);
});

/** Seed a raw database notification row (skips the queued/broadcast path). */
function seedAdminNotification(Admin $admin, string $category, string $title, bool $unread = true, ?string $url = null): string
{
    $id = Str::uuid()->toString();
    $admin->notifications()->create([
        'id' => $id,
        'type' => OperatorNotification::class,
        'data' => ['title' => $title, 'body' => 'Body', 'category' => $category, 'url' => $url],
        'read_at' => $unread ? null : now(),
    ]);

    return $id;
}

it('renders the redesigned feed with category chips and grouped rows', function () {
    seedAdminNotification($this->admin, 'security', 'Insolvency detected');
    seedAdminNotification($this->admin, 'deposit', 'Manual deposit awaiting review');

    actingAs($this->admin, 'admin')->get(route('admin.notifications'))
        ->assertOk()
        ->assertSee('Insolvency detected')
        ->assertSee('Manual deposit awaiting review')
        ->assertSee('Security')     // category chip + badge
        ->assertSee('Deposits')
        ->assertSee('Today');       // date bucket heading
});

// NOTE: the topbar bell lists recent items (as mark-read forms) on every page, so a
// feed-visible item's read-route appears twice (bell + feed) and an excluded one once
// (bell only). Counting occurrences isolates the feed from the always-present bell.
it('filters the feed to unread only', function () {
    $unread = seedAdminNotification($this->admin, 'security', 'Unread alert', unread: true);
    $read = seedAdminNotification($this->admin, 'deposit', 'Already seen', unread: false);

    $html = actingAs($this->admin, 'admin')->get(route('admin.notifications', ['filter' => 'unread']))
        ->assertOk()->getContent();

    expect(substr_count($html, route('admin.notifications.read', $unread)))->toBe(2)  // bell + feed
        ->and(substr_count($html, route('admin.notifications.read', $read)))->toBe(1); // bell only
});

it('filters the feed by category', function () {
    $compliance = seedAdminNotification($this->admin, 'compliance', 'Compliance case raised');
    $merchant = seedAdminNotification($this->admin, 'merchant', 'New merchant application');

    $html = actingAs($this->admin, 'admin')->get(route('admin.notifications', ['filter' => 'compliance']))
        ->assertOk()->getContent();

    expect(substr_count($html, route('admin.notifications.read', $compliance)))->toBe(2)  // bell + feed
        ->and(substr_count($html, route('admin.notifications.read', $merchant)))->toBe(1); // bell only
});

it('marks a notification read and follows its local deep link', function () {
    $id = seedAdminNotification($this->admin, 'kyc', 'KYC to review', url: '/admin/kyc');

    actingAs($this->admin, 'admin')
        ->from(route('admin.notifications'))
        ->post(route('admin.notifications.read', $id))
        ->assertRedirect('/admin/kyc');

    expect($this->admin->fresh()->unreadNotifications()->count())->toBe(0);
});

it('marks read from the topbar bell so the unread count updates', function () {
    $id = seedAdminNotification($this->admin, 'security', 'Bell alert', url: '/admin/security');

    // The bell (on every admin page) renders each item as a mark-read POST form.
    actingAs($this->admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.notifications.read', $id))
        ->assertSee('Bell alert');

    expect($this->admin->fresh()->unreadNotifications()->count())->toBe(1);

    // Clicking it marks read + follows the deep link; the badge then clears.
    actingAs($this->admin, 'admin')->post(route('admin.notifications.read', $id))
        ->assertRedirect('/admin/security');

    expect($this->admin->fresh()->unreadNotifications()->count())->toBe(0);
});

it('ignores an off-host deep link and lands on the feed', function () {
    $id = seedAdminNotification($this->admin, 'general', 'Sketchy link', url: 'https://evil.example/phish');

    actingAs($this->admin, 'admin')
        ->from(route('admin.notifications'))
        ->post(route('admin.notifications.read', $id))
        ->assertRedirect(route('admin.notifications'));
});
