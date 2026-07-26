<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Captcha\Captcha;
use App\Support\Captcha\CaptchaService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a reCAPTCHA token for a given feature. Passes automatically when the
 * feature's captcha is disabled. Use via {@see Captcha::rule()}.
 */
class CaptchaResponse implements ValidationRule
{
    public function __construct(private readonly string $feature) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! app(CaptchaService::class)->verify(is_string($value) ? $value : null, $this->feature)) {
            $fail(__('Captcha verification failed. Please try again.'));
        }
    }
}
