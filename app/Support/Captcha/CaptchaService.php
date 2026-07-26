<?php

declare(strict_types=1);

namespace App\Support\Captcha;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Google reCAPTCHA verification + config, driven entirely by Admin Settings. The
 * one place server-side verification lives — controllers, the CaptchaResponse rule
 * and the VerifyCaptcha middleware all call {@see verify()}. No hardcoded keys.
 *
 * Fail policy: an inconclusive network/timeout error fails OPEN (don't lock users
 * out during a Google outage — logged); an actual `success:false`, low v3 score,
 * replay, or missing token fails CLOSED.
 */
class CaptchaService
{
    public const V2_CHECKBOX = 'v2_checkbox';

    public const V2_INVISIBLE = 'v2_invisible';

    public const V3 = 'v3';

    /** Globally on AND fully configured (keys present). */
    public function enabled(): bool
    {
        return (bool) getSetting('captcha_enabled', false)
            && filled($this->siteKey())
            && filled(getSetting('captcha_secret_key'));
    }

    /** On for this specific feature (global enable + feature toggle). */
    public function enabledFor(string $feature): bool
    {
        return $this->enabled() && in_array($feature, $this->activeFeatures(), true);
    }

    public function provider(): string
    {
        return (string) getSetting('captcha_provider', self::V2_CHECKBOX);
    }

    public function siteKey(): string
    {
        return (string) getSetting('captcha_site_key', '');
    }

    public function minScore(): float
    {
        return (float) getSetting('captcha_min_score', 0.5);
    }

    /** @return list<string> */
    public function activeFeatures(): array
    {
        $features = getSetting('captcha_features', []);

        return is_array($features) ? array_values($features) : [];
    }

    /**
     * Server-side verify a token for a feature. Returns true when the feature's
     * captcha is disabled (so the app behaves exactly as before).
     */
    public function verify(?string $token, string $feature, ?string $ip = null): bool
    {
        if (! $this->enabledFor($feature)) {
            return true;
        }

        $ip ??= request()->ip();
        $token = trim((string) $token);

        if ($token === '') {
            $this->logFailure($feature, $ip, ['missing-token']);

            return false;
        }

        // Abuse guard on the verify path itself.
        $rlKey = 'captcha:verify:'.$ip;
        if (RateLimiter::tooManyAttempts($rlKey, (int) config('captcha.rate_limit', 60))) {
            $this->logFailure($feature, $ip, ['rate-limited']);

            return false;
        }
        RateLimiter::hit($rlKey, 60);

        // Replay guard — a token may only be spent once.
        $seen = 'captcha:used:'.hash('sha256', $token);
        if (Cache::has($seen)) {
            $this->logFailure($feature, $ip, ['replay']);

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('captcha.timeout', 5))
                ->post((string) config('captcha.verify_url'), [
                    'secret' => (string) getSetting('captcha_secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (Throwable $e) {
            // Google unreachable / timed out — fail open (availability > friction).
            Log::warning('reCAPTCHA verify unreachable — failing open', ['feature' => $feature, 'error' => $e->getMessage()]);

            return true;
        }

        if ($response->failed()) {
            Log::warning('reCAPTCHA verify HTTP error — failing open', ['feature' => $feature, 'status' => $response->status()]);

            return true;
        }

        $data = (array) $response->json();
        $ok = (bool) ($data['success'] ?? false);

        if ($ok && $this->provider() === self::V3) {
            $ok = ((float) ($data['score'] ?? 0)) >= $this->minScore();
        }

        if ($ok) {
            Cache::put($seen, true, now()->addSeconds((int) config('captcha.replay_ttl', 180)));
        } else {
            $this->logFailure($feature, $ip, $data['error-codes'] ?? ['verification-failed'], $data['score'] ?? null);
        }

        return $ok;
    }

    /** @param  array<int, string>  $errors */
    private function logFailure(string $feature, ?string $ip, array $errors, ?float $score = null): void
    {
        Log::warning('reCAPTCHA verification failed', array_filter([
            'feature' => $feature,
            'ip' => $ip,
            'errors' => $errors,
            'score' => $score,
        ], static fn ($v) => $v !== null));
    }
}
