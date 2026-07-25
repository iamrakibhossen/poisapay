<?php

declare(strict_types=1);

namespace App\Sell\Actions\SalesPage;

use App\Sell\DTOs\SalesPageData;
use App\Sell\Models\SalesPage;

/**
 * Update a page's builder config. Bumps `version` (a cache-bust stamp) and lets
 * the SalesPage `saved` observer drop the cached public view — automatically.
 */
class UpdateSalesPage
{
    public function execute(SalesPage $page, SalesPageData $data): SalesPage
    {
        $page->update([
            'name' => $data->name,
            'sections' => $data->sections,
            'theme' => $data->theme,
            'seo' => $data->seo,
            'tracking' => $data->tracking,
            'version' => $page->version + 1,
        ]);

        return $page->refresh();
    }
}
