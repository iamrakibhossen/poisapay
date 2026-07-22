<?php

declare(strict_types=1);

use App\Domain\Notification\BroadcastAnnouncementAction;
use App\Domain\Notification\NotificationService;
use App\Enums\KycTier;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'NotificationTemplateSeeder', '--force' => true]);
    $this->user = User::factory()->create(['locale' => 'en']);
});

it('renders a template with placeholders and strips unresolved tokens', function () {
    $template = NotificationTemplate::resolve('deposit.credited');
    $rendered = $template->render(['amount' => '50.00 USDT', 'asset' => 'USDT']);

    expect($rendered['body'])->toBe('50.00 USDT has landed in your USDT wallet.');
});

it('delivers in-app + email by default', function () {
    Notification::fake();

    app(NotificationService::class)->send($this->user, 'deposit.credited', ['amount' => '10 USDT', 'asset' => 'USDT']);

    Notification::assertSentTo($this->user, UserNotification::class, function ($n, $channels) {
        return in_array('database', $channels, true) && in_array('mail', $channels, true);
    });
});

it('respects an email opt-out for a category', function () {
    NotificationPreference::create([
        'user_id' => $this->user->id, 'category' => 'money', 'in_app' => true, 'email' => false,
    ]);
    Notification::fake();

    app(NotificationService::class)->send($this->user, 'deposit.credited', ['amount' => '10 USDT', 'asset' => 'USDT']);

    Notification::assertSentTo($this->user, UserNotification::class, function ($n, $channels) {
        return in_array('database', $channels, true) && ! in_array('mail', $channels, true);
    });
});

it('never suppresses a security-category notification', function () {
    NotificationPreference::create([
        'user_id' => $this->user->id, 'category' => 'security', 'in_app' => false, 'email' => false,
    ]);
    Notification::fake();

    app(NotificationService::class)->send($this->user, 'kyc.approved', ['tier' => 'Full']);

    Notification::assertSentTo($this->user, UserNotification::class, function ($n, $channels) {
        return in_array('database', $channels, true) && in_array('mail', $channels, true);
    });
});

it('does not send when the user has muted every channel for a non-security category', function () {
    NotificationPreference::create([
        'user_id' => $this->user->id, 'category' => 'product', 'in_app' => false, 'email' => false,
    ]);
    Notification::fake();

    app(NotificationService::class)->send($this->user, 'reward.granted', ['amount' => '5 USDT', 'reason' => 'cashback']);

    Notification::assertNothingSent();
});

it('broadcasts an announcement to a segment and records recipients', function () {
    User::factory()->count(3)->create(['kyc_tier' => KycTier::Full]);
    User::factory()->count(2)->create(['kyc_tier' => KycTier::Unverified]);
    $admin = Admin::create(['name' => 'Op', 'email' => 'op10@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    Notification::fake();

    $announcement = app(BroadcastAnnouncementAction::class)->execute(
        $admin, 'Scheduled maintenance', 'We will be down at midnight.', 'kyc_full', ['in_app', 'email'], 'product'
    );

    // Only the 3 Full-KYC users (the beforeEach user is Unverified).
    expect($announcement->recipients)->toBe(3)
        ->and(Announcement::count())->toBe(1);
    Notification::assertSentTimes(UserNotification::class, 3);
});
