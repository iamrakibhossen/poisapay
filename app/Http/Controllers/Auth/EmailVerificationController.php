<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Audit\ActivityLogger;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Confirms a signed email-verification link (§8). Marks the address verified,
 * writes an audit entry, and returns the user to the dashboard.
 */
class EmailVerificationController
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->fulfill();

        ActivityLogger::log('email.verified', $request->user());

        return redirect()->route('dashboard')->with('status', 'email-verified');
    }
}
