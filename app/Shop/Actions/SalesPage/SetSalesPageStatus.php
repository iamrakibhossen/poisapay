<?php

declare(strict_types=1);

namespace App\Shop\Actions\SalesPage;

use App\Shop\Enums\SalesPageStatus;
use App\Shop\Events\SalesPageStatusChanged;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\SalesPage;

/** Publish / archive a sales page. Publishing requires the product to be buyable. */
class SetSalesPageStatus
{
    public function execute(SalesPage $page, SalesPageStatus $to): SalesPage
    {
        $from = $page->status;

        if ($to === SalesPageStatus::Published && ! $page->product?->status->isBuyable()) {
            throw ShopException::notPublishable('its product is not published');
        }

        $page->update([
            'status' => $to,
            'published_at' => $to === SalesPageStatus::Published && ! $page->published_at
                ? now()
                : $page->published_at,
        ]);

        SalesPageStatusChanged::dispatch($page, $from, $to);

        return $page->refresh();
    }
}
