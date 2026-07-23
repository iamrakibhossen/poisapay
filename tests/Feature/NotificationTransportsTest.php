<?php

declare(strict_types=1);

use App\Domain\Notification\NotificationTransportManager;
use App\Domain\Notification\Transports\LogSmsTransport;
use App\Domain\Notification\Transports\TwilioSmsTransport;
use App\Models\User;
use App\Models\UserPushToken;
use Illuminate\Support\Facades\Http;

it('defaults the SMS channel to the log stub', function () {
    expect(app(NotificationTransportManager::class)->for('sms'))->toBeInstanceOf(LogSmsTransport::class);
});

it('resolves and sends via the Twilio SMS transport', function () {
    config([
        'providers.notifications.sms' => 'twilio',
        'services.twilio.sid' => 'AC0000000000000000000000000000',
        'services.twilio.token' => 'secret-token',
        'services.twilio.from' => '+15550000000',
    ]);
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);

    $manager = app(NotificationTransportManager::class);
    expect($manager->for('sms'))->toBeInstanceOf(TwilioSmsTransport::class);

    $user = User::factory()->create(['phone' => '+8801700000000']);
    $manager->dispatch('sms', $user, 'Security alert', 'A new device signed in.');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'api.twilio.com')
        && $req['To'] === '+8801700000000'
        && str_contains((string) $req['Body'], 'Security alert'));
});

it('does not call Twilio when the account is unconfigured', function () {
    config(['providers.notifications.sms' => 'twilio', 'services.twilio.sid' => '', 'services.twilio.token' => '', 'services.twilio.from' => '']);
    Http::fake();

    $user = User::factory()->create(['phone' => '+8801700000000']);
    app(NotificationTransportManager::class)->dispatch('sms', $user, 'T', 'B');

    Http::assertNothingSent();
});

it('sends push notifications to registered FCM tokens', function () {
    config(['providers.notifications.push' => 'fcm', 'services.fcm.key' => 'srv-key']);
    Http::fake(['fcm.googleapis.com/*' => Http::response(['success' => 1])]);

    $user = User::factory()->create();
    UserPushToken::create(['user_id' => $user->id, 'token' => 'device-tok-1', 'platform' => 'android']);

    app(NotificationTransportManager::class)->dispatch('push', $user, 'Alert', 'You have a new deposit.');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'fcm.googleapis.com')
        && in_array('device-tok-1', $req['registration_ids'], true));
});

it('sends WhatsApp via Twilio', function () {
    config([
        'providers.notifications.whatsapp' => 'twilio',
        'services.twilio.sid' => 'AC1', 'services.twilio.token' => 'tok',
        'services.twilio.whatsapp_from' => 'whatsapp:+14155238886',
    ]);
    Http::fake(['api.twilio.com/*' => Http::response([], 201)]);

    $user = User::factory()->create(['phone' => '+8801700000000']);
    app(NotificationTransportManager::class)->dispatch('whatsapp', $user, 'Hi', 'Body');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'api.twilio.com') && $req['To'] === 'whatsapp:+8801700000000');
});

it('sends Telegram via the Bot API to a linked chat', function () {
    config(['providers.notifications.telegram' => 'telegram', 'services.telegram.bot_token' => 'bot123']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    $user = User::factory()->create(['telegram_chat_id' => '99887766']);
    app(NotificationTransportManager::class)->dispatch('telegram', $user, 'Hi', 'Body');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'api.telegram.org/botbot123/sendMessage')
        && $req['chat_id'] === '99887766');
});
