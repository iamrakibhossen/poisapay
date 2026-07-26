<?php

declare(strict_types=1);

namespace App\Support\Captcha;

use App\Rules\CaptchaResponse;

/**
 * Thin static entry point over {@see CaptchaService} — `Captcha::verify(...)`,
 * `Captcha::rule('login')`, etc. Keeps controllers/form-requests clean and gives
 * one import for callers.
 */
final class Captcha
{
    public static function service(): CaptchaService
    {
        return app(CaptchaService::class);
    }

    public static function enabled(): bool
    {
        return self::service()->enabled();
    }

    public static function enabledFor(string $feature): bool
    {
        return self::service()->enabledFor($feature);
    }

    public static function verify(?string $token, string $feature, ?string $ip = null): bool
    {
        return self::service()->verify($token, $feature, $ip);
    }

    public static function siteKey(): string
    {
        return self::service()->siteKey();
    }

    public static function provider(): string
    {
        return self::service()->provider();
    }

    /**
     * Validation rules for the `g-recaptcha-response` field of a feature — required
     * (can't be bypassed by omitting it) when enabled, skipped otherwise.
     *
     * @return array<int, mixed>
     */
    public static function rule(string $feature): array
    {
        return self::service()->enabledFor($feature)
            ? ['required', 'string', new CaptchaResponse($feature)]
            : ['nullable'];
    }
}
