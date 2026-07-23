<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Hides the whole P2P surface (404) unless the `p2p_enabled` flag is on. */
class EnsureP2pEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(feature('p2p_enabled', false), 404);

        return $next($request);
    }
}
