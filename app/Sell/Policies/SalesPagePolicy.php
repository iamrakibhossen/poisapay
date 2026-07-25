<?php

declare(strict_types=1);

namespace App\Sell\Policies;

use App\Models\Admin;
use App\Sell\Models\SalesPage;
use Illuminate\Contracts\Auth\Authenticatable;

/** A seller may only touch their own sales pages; operators may view any. */
class SalesPagePolicy
{
    public function view(Authenticatable $actor, SalesPage $page): bool
    {
        return $this->owns($actor, $page) || $actor instanceof Admin;
    }

    public function update(Authenticatable $actor, SalesPage $page): bool
    {
        return $this->owns($actor, $page);
    }

    public function delete(Authenticatable $actor, SalesPage $page): bool
    {
        return $this->owns($actor, $page);
    }

    private function owns(Authenticatable $actor, SalesPage $page): bool
    {
        return ! $actor instanceof Admin && $page->seller->user_id === $actor->getAuthIdentifier();
    }
}
