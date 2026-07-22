<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-console gate. Makes the `admin` guard the default for the request so all
 * downstream auth()/Gate/can() calls resolve against the operator (TDD §9.2),
 * requires an authenticated, active admin, and bounces guests to admin login.
 */
class EnsureOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('admin');

        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return redirect()->route('admin.login');
        }

        abort_if(! $admin->is_active, 403, 'This operator account is disabled.');

        return $next($request);
    }
}
