<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\P2p\P2pPresenceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the authenticated merchant active on any P2P request (throttled inside
 * the service). Runs after the response is sent so it never adds latency.
 */
class TrackP2pPresence
{
    public function __construct(private readonly P2pPresenceService $presence) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($user = $request->user()) {
            $this->presence->markActive((string) $user->getKey());
        }
    }
}
