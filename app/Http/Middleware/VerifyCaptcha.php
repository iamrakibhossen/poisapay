<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Captcha\Captcha;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level reCAPTCHA guard: `->middleware('captcha:feature')`. Verifies the
 * `g-recaptcha-response` token unconditionally (can't be bypassed by omitting the
 * field). No-op when the feature's captcha is disabled. Isolated from business logic.
 */
class VerifyCaptcha
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (Captcha::verify($request->input('g-recaptcha-response'), $feature, $request->ip())) {
            return $next($request);
        }

        $message = __('Captcha verification failed. Please try again.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['g-recaptcha-response' => [$message]]], 422);
        }

        return back()->withInput($request->except('g-recaptcha-response'))
            ->withErrors(['g-recaptcha-response' => $message]);
    }
}
