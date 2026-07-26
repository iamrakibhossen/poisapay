<?php

declare(strict_types=1);

namespace App\Shop\Http\Middleware;

use App\Shop\Services\Domain\DomainResolver;
use App\Shop\Support\PlatformHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves custom domains. Runs as *global* middleware (before routing) so it can
 * rewrite the request onto the existing funnel routes — zero route duplication:
 *
 *   example.com/           → /p/{slug}
 *   www.example.com/checkout → /p/{slug}/checkout
 *
 * Platform hosts pass straight through untouched (one cached host check). Any
 * other host that isn't a serviceable custom domain gets a 404 — we never serve
 * platform content under an unrecognized Host header (host-header/takeover guard).
 */
class ResolveShopDomain
{
    public function __construct(private readonly DomainResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (PlatformHost::is($host)) {
            return $next($request);
        }

        // A non-platform host only reaches us via a merchant's DNS. If the feature
        // is off or the domain isn't serviceable, refuse rather than leak the app.
        if (! feature('shop_custom_domains', false)) {
            abort(404);
        }

        $match = $this->resolver->resolve($host);

        if ($match === null) {
            abort(404);
        }

        return $next($this->rewriteToPage($request, $match['slug']));
    }

    /** Rewrite the request URI onto the funnel route for the domain's page. */
    private function rewriteToPage(Request $request, string $slug): Request
    {
        $suffix = rtrim($request->getPathInfo(), '/');   // "" | "/checkout" | ...
        $prefix = '/p/'.$slug;

        // URLs generated on the custom host already carry the full `/p/{slug}` funnel
        // path (route() uses the request host), e.g. the Buy form posts to
        // shop.example.com/p/{slug}/buy. Pass those through as-is instead of adding a
        // second `/p/{slug}` — otherwise every checkout action would 404. A bare
        // suffix (shop.example.com/checkout) is mapped onto the page as before.
        $path = ($suffix === $prefix || str_starts_with($suffix, $prefix.'/'))
            ? $suffix
            : $prefix.$suffix;

        $query = $request->getQueryString();
        $uri = $path.($query !== null && $query !== '' ? '?'.$query : '');

        $server = $request->server->all();
        $server['REQUEST_URI'] = $uri;

        // Passing a fresh server bag makes Symfony recompute the cached
        // pathInfo/requestUri/method, so the router matches the rewritten path.
        $rewritten = $request->duplicate(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
        );
        $rewritten->attributes->set('shop_custom_domain', $request->getHost());

        return $rewritten;
    }
}
