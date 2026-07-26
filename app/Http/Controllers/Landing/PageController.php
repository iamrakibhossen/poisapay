<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Seo\SeoData;
use Illuminate\Contracts\View\View;

/**
 * Public CMS page — server-rendered Blade. 404s on unpublished or unknown slugs.
 */
final class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $seo = SeoData::make($page->title, $page->meta_description)
            ->withCanonical(route('page.show', $page->slug))
            ->withBreadcrumbs([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $page->title, 'url' => route('page.show', $page->slug)],
            ]);

        return view('landing::page', ['page' => $page, 'seo' => $seo]);
    }
}
