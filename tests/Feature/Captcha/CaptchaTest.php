<?php

declare(strict_types=1);

use App\Support\Captcha\CaptchaService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function enableCaptcha(array $features = ['login', 'register'], string $provider = 'v2_checkbox'): void
{
    updateSetting('captcha_enabled', true);
    updateSetting('captcha_provider', $provider);
    updateSetting('captcha_site_key', 'test-site-key');
    updateSetting('captcha_secret_key', 'test-secret-key');
    updateSetting('captcha_min_score', 0.5);
    updateSetting('captcha_features', $features);
}

function fakeSiteverify(array $body): void
{
    Http::fake(['*recaptcha/api/siteverify*' => Http::response($body, 200)]);
}

it('passes verification when the feature is disabled (works exactly as before)', function () {
    // Nothing configured → optional → verify() is a no-op pass.
    expect(app(CaptchaService::class)->verify(null, 'login', '1.2.3.4'))->toBeTrue();

    // Enabled globally but not for this feature.
    enableCaptcha(['register']);
    expect(app(CaptchaService::class)->enabledFor('login'))->toBeFalse();
    expect(app(CaptchaService::class)->verify(null, 'login', '1.2.3.4'))->toBeTrue();
});

it('is only enabled when keys are present', function () {
    updateSetting('captcha_enabled', true);
    updateSetting('captcha_features', ['login']);
    expect(app(CaptchaService::class)->enabled())->toBeFalse(); // no keys

    updateSetting('captcha_site_key', 'k');
    updateSetting('captcha_secret_key', 's');
    expect(app(CaptchaService::class)->enabled())->toBeTrue();
});

it('verifies a valid token', function () {
    enableCaptcha();
    fakeSiteverify(['success' => true]);
    expect(app(CaptchaService::class)->verify('good', 'login', '1.2.3.4'))->toBeTrue();
});

it('rejects an invalid token', function () {
    enableCaptcha();
    fakeSiteverify(['success' => false, 'error-codes' => ['invalid-input-response']]);
    expect(app(CaptchaService::class)->verify('bad', 'login', '9.9.9.9'))->toBeFalse();
});

it('rejects a missing token when enabled', function () {
    enableCaptcha();
    expect(app(CaptchaService::class)->verify('', 'login', '1.2.3.4'))->toBeFalse();
});

it('accepts a v3 token above the minimum score', function () {
    enableCaptcha(['login'], 'v3');
    updateSetting('captcha_min_score', 0.6);
    fakeSiteverify(['success' => true, 'score' => 0.9]);
    expect(app(CaptchaService::class)->verify('t1', 'login', '1.1.1.1'))->toBeTrue();
});

it('rejects a v3 token below the minimum score', function () {
    enableCaptcha(['login'], 'v3');
    updateSetting('captcha_min_score', 0.6);
    fakeSiteverify(['success' => true, 'score' => 0.3]);
    expect(app(CaptchaService::class)->verify('t2', 'login', '1.1.1.2'))->toBeFalse();
});

it('blocks token replay', function () {
    enableCaptcha();
    fakeSiteverify(['success' => true]);

    expect(app(CaptchaService::class)->verify('same-token', 'login', '1.2.3.4'))->toBeTrue();
    expect(app(CaptchaService::class)->verify('same-token', 'login', '1.2.3.4'))->toBeFalse(); // replay
});

it('fails open when Google is unreachable', function () {
    enableCaptcha();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect(app(CaptchaService::class)->verify('tok', 'login', '1.2.3.4'))->toBeTrue();
});

it('blocks login when captcha is enabled and no token is supplied', function () {
    enableCaptcha();

    $this->post(route('login.attempt'), ['email' => 'a@b.com', 'password' => 'secret'])
        ->assertSessionHasErrors('g-recaptcha-response');
});

it('lets login proceed past captcha with a valid token', function () {
    enableCaptcha();
    fakeSiteverify(['success' => true]);

    // No such user → fails on credentials, NOT on captcha (captcha passed).
    $this->post(route('login.attempt'), ['email' => 'nope@b.com', 'password' => 'secret', 'g-recaptcha-response' => 'good'])
        ->assertSessionHasErrors('email')
        ->assertSessionDoesntHaveErrors('g-recaptcha-response');
});

it('leaves login untouched when captcha is disabled', function () {
    // Disabled → no captcha error even without a token.
    $this->post(route('login.attempt'), ['email' => 'nope@b.com', 'password' => 'secret'])
        ->assertSessionHasErrors('email')
        ->assertSessionDoesntHaveErrors('g-recaptcha-response');
});

it('renders the widget only when enabled for the page', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('g-recaptcha', false);

    enableCaptcha();
    $this->get(route('login'))->assertOk()->assertSee('g-recaptcha', false)->assertSee('test-site-key', false);
});
