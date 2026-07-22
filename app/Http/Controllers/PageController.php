<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;

/**
 * Public CMS page — plain server-rendered Blade (no Livewire on the
 * user-facing frontend). 404s on unpublished or unknown slugs.
 */
class PageController
{
    public function show(string $slug): View
    {
        $page = Page::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return view('marketing.page', ['page' => $page]);
    }
}
