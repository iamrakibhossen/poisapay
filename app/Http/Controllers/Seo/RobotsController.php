<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Dynamic robots.txt — allows the public surface, disallows every private/auth/
 * app/admin path (config/seo.php `noindex_prefixes`), and points at the sitemap.
 * On non-production hosts the whole site is disallowed to keep staging out of
 * search indexes.
 */
final class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (app()->environment('production')) {
            foreach ((array) config('seo.noindex_prefixes', []) as $prefix) {
                $lines[] = 'Disallow: /'.trim($prefix, '/').'/';
            }
            $lines[] = 'Disallow: /*?*';                 // avoid crawling query-string dupes
            $lines[] = 'Allow: /';
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        } else {
            // Staging / local — keep everything out of the index.
            $lines[] = 'Disallow: /';
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
