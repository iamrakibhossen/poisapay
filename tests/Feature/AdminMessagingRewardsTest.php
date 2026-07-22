<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\NotificationTemplate;
use App\Models\RewardCampaign;
use App\Models\RewardGrant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Roles live on the admin guard.
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    // Full operator (super-admin passes every gate).
    $this->operator = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->operator->syncRoles(['super-admin']);
});

// ---- Page loads ------------------------------------------------------------

it('loads the messaging page for an operator', function () {
    actingAs($this->operator, 'admin')->get(route('admin.messaging'))->assertOk();
    actingAs($this->operator, 'admin')->get(route('admin.messaging', ['tab' => 'announcements']))->assertOk();
});

it('loads the rewards page for an operator', function () {
    actingAs($this->operator, 'admin')->get(route('admin.rewards'))->assertOk();
    actingAs($this->operator, 'admin')->get(route('admin.rewards', ['tab' => 'grants']))->assertOk();
    actingAs($this->operator, 'admin')->get(route('admin.rewards', ['tab' => 'referrals']))->assertOk();
});

// ---- Messaging: templates --------------------------------------------------

it('creates and updates a notification template', function () {
    actingAs($this->operator, 'admin')->post(route('admin.messaging.template.save'), [
        'key' => 'deposit.credited',
        'locale' => 'en',
        'name' => 'Deposit credited',
        'category' => 'money',
        'channels' => ['in_app', 'email'],
        'subject' => 'Your deposit is in',
        'body' => 'Hi {{name}}',
        'is_active' => '1',
    ])->assertRedirect(route('admin.messaging'))->assertSessionHas('success');

    $template = NotificationTemplate::where('key', 'deposit.credited')->first();
    expect($template)->not->toBeNull()
        ->and($template->name)->toBe('Deposit credited')
        ->and($template->channels)->toBe(['in_app', 'email'])
        ->and($template->is_active)->toBeTrue();

    // Update the existing template via the hidden id.
    actingAs($this->operator, 'admin')->post(route('admin.messaging.template.save'), [
        'id' => $template->id,
        'key' => 'deposit.credited',
        'locale' => 'en',
        'name' => 'Deposit landed',
        'category' => 'money',
        'channels' => ['in_app'],
        'body' => 'Updated body',
        'is_active' => '1',
    ])->assertRedirect(route('admin.messaging'))->assertSessionHas('success');

    expect(NotificationTemplate::count())->toBe(1)
        ->and($template->fresh()->name)->toBe('Deposit landed')
        ->and($template->fresh()->channels)->toBe(['in_app']);
});

it('validates the template form', function () {
    actingAs($this->operator, 'admin')->post(route('admin.messaging.template.save'), [
        'key' => '',
        'locale' => 'en',
        'body' => '',
    ])->assertSessionHasErrors(['key', 'name', 'body']);

    expect(NotificationTemplate::count())->toBe(0);
});

it('toggles a template active flag', function () {
    $template = NotificationTemplate::create([
        'key' => 'x.y', 'locale' => 'en', 'name' => 'X', 'category' => 'product',
        'channels' => ['in_app'], 'body' => 'b', 'is_active' => true,
    ]);

    actingAs($this->operator, 'admin')->post(route('admin.messaging.template.toggle', $template->id))
        ->assertRedirect(route('admin.messaging'))->assertSessionHas('success');

    expect($template->fresh()->is_active)->toBeFalse();

    actingAs($this->operator, 'admin')->post(route('admin.messaging.template.toggle', $template->id));
    expect($template->fresh()->is_active)->toBeTrue();
});

// ---- Messaging: announcements ----------------------------------------------

it('sends an announcement to a segment', function () {
    User::factory()->count(3)->create();

    actingAs($this->operator, 'admin')->post(route('admin.messaging.announcement.send'), [
        'annTitle' => 'Scheduled maintenance',
        'annBody' => 'Tonight at 2am.',
        'annSegment' => 'all',
        'annChannels' => ['in_app'],
        'annCategory' => 'product',
    ])->assertRedirect(route('admin.messaging', ['tab' => 'announcements']))->assertSessionHas('success');

    $announcement = Announcement::first();
    expect($announcement)->not->toBeNull()
        ->and($announcement->title)->toBe('Scheduled maintenance')
        ->and($announcement->segment)->toBe('all')
        ->and($announcement->recipients)->toBe(3)
        ->and($announcement->sent_by)->toBe($this->operator->id);
});

it('validates the announcement form', function () {
    actingAs($this->operator, 'admin')->post(route('admin.messaging.announcement.send'), [
        'annTitle' => '',
        'annBody' => '',
        'annSegment' => 'nope',
        'annChannels' => [],
    ])->assertSessionHasErrors(['annTitle', 'annBody', 'annSegment', 'annChannels']);

    expect(Announcement::count())->toBe(0);
});

// ---- Rewards: campaigns ----------------------------------------------------

it('creates and updates a reward campaign', function () {
    $asset = testAsset('USDT', 6, 'tron');

    actingAs($this->operator, 'admin')->post(route('admin.rewards.campaign.save'), [
        'key' => 'welcome',
        'name' => 'Welcome bonus',
        'type' => 'fixed',
        'asset_id' => $asset->id,
        'amount' => '5.00',
        'is_active' => '1',
    ])->assertRedirect(route('admin.rewards'))->assertSessionHas('success');

    $campaign = RewardCampaign::where('key', 'welcome')->first();
    expect($campaign)->not->toBeNull()
        ->and($campaign->type)->toBe('fixed')
        // 5.00 USDT at 6 decimals => 5000000 base units.
        ->and($campaign->amount)->toBe('5000000')
        ->and($campaign->is_active)->toBeTrue();

    // Update: switch to a percentage campaign via the hidden id.
    actingAs($this->operator, 'admin')->post(route('admin.rewards.campaign.save'), [
        'id' => $campaign->id,
        'key' => 'welcome',
        'name' => 'Welcome cashback',
        'type' => 'percentage',
        'rate_bps' => '250',
        'is_active' => '1',
    ])->assertRedirect(route('admin.rewards'))->assertSessionHas('success');

    expect(RewardCampaign::count())->toBe(1)
        ->and($campaign->fresh()->name)->toBe('Welcome cashback')
        ->and($campaign->fresh()->type)->toBe('percentage')
        ->and($campaign->fresh()->rate_bps)->toBe(250)
        ->and($campaign->fresh()->amount)->toBeNull();
});

it('validates a fixed campaign requires an asset and amount', function () {
    actingAs($this->operator, 'admin')->post(route('admin.rewards.campaign.save'), [
        'key' => 'welcome',
        'name' => 'Welcome',
        'type' => 'fixed',
    ])->assertSessionHasErrors(['asset_id', 'amount']);

    expect(RewardCampaign::count())->toBe(0);
});

it('toggles a campaign active flag', function () {
    $campaign = RewardCampaign::create([
        'key' => 'cashback', 'name' => 'CB', 'type' => 'percentage', 'rate_bps' => 50, 'is_active' => true,
    ]);

    actingAs($this->operator, 'admin')->post(route('admin.rewards.campaign.toggle', $campaign->id))
        ->assertRedirect(route('admin.rewards'))->assertSessionHas('success');

    expect($campaign->fresh()->is_active)->toBeFalse();

    actingAs($this->operator, 'admin')->post(route('admin.rewards.campaign.toggle', $campaign->id));
    expect($campaign->fresh()->is_active)->toBeTrue();
});

// ---- Rewards: manual grant -------------------------------------------------

it('grants a reward to a user and credits the ledger', function () {
    $asset = testAsset('USDT', 6, 'tron');
    $user = User::factory()->create();

    actingAs($this->operator, 'admin')->post(route('admin.rewards.grant'), [
        'grantEmail' => $user->email,
        'grantAssetId' => $asset->id,
        'grantAmount' => '5.00',
        'grantReason' => 'Goodwill',
    ])->assertRedirect(route('admin.rewards', ['tab' => 'grants']))->assertSessionHas('success');

    $grant = RewardGrant::where('user_id', $user->id)->first();
    expect($grant)->not->toBeNull()
        ->and($grant->type)->toBe('manual')
        ->and($grant->amount)->toBe('5000000');

    // The grant is a real treasury payout: the user's available balance rises.
    expect(app(LedgerService::class)->availableBalance($user, $asset->id)->baseString())->toBe('5000000');
});

it('rejects a grant to an unknown email', function () {
    $asset = testAsset('USDT', 6, 'tron');

    actingAs($this->operator, 'admin')->post(route('admin.rewards.grant'), [
        'grantEmail' => 'nobody@poisapay.test',
        'grantAssetId' => $asset->id,
        'grantAmount' => '5.00',
    ])->assertSessionHasErrors('grantEmail');

    expect(RewardGrant::count())->toBe(0);
});

// ---- Authorization ---------------------------------------------------------

it('forbids an operator without the messaging permission', function () {
    // The support role has neither manage-settings nor super-admin.
    $support = Admin::create([
        'name' => 'Sup', 'email' => 'sup@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $support->syncRoles(['support']);

    actingAs($support, 'admin')->get(route('admin.messaging'))->assertForbidden();
    actingAs($support, 'admin')->post(route('admin.messaging.announcement.send'), [
        'annTitle' => 'x', 'annBody' => 'x', 'annSegment' => 'all', 'annChannels' => ['in_app'], 'annCategory' => 'product',
    ])->assertForbidden();
});

it('forbids an operator without the rewards manage permission', function () {
    // The admin role has view-rewards (page loads) but NOT manage-rewards (mutations 403).
    $viewer = Admin::create([
        'name' => 'Viewer', 'email' => 'viewer@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $viewer->syncRoles(['admin']);
    $asset = testAsset('USDT', 6, 'tron');
    $user = User::factory()->create();

    actingAs($viewer, 'admin')->get(route('admin.rewards'))->assertOk();

    actingAs($viewer, 'admin')->post(route('admin.rewards.grant'), [
        'grantEmail' => $user->email, 'grantAssetId' => $asset->id, 'grantAmount' => '5.00',
    ])->assertForbidden();

    actingAs($viewer, 'admin')->post(route('admin.rewards.campaign.save'), [
        'key' => 'welcome', 'name' => 'W', 'type' => 'percentage', 'rate_bps' => '100',
    ])->assertForbidden();

    expect(RewardGrant::count())->toBe(0)
        ->and(RewardCampaign::count())->toBe(0);
});
