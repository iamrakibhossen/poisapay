<?php

declare(strict_types=1);

namespace App\Shop\Policies;

use App\Models\Admin;
use App\Shop\Models\Product;
use Illuminate\Contracts\Auth\Authenticatable;

/** A seller may only touch their own products; operators may view any. */
class ProductPolicy
{
    public function view(Authenticatable $actor, Product $product): bool
    {
        return $this->owns($actor, $product) || $actor instanceof Admin;
    }

    public function update(Authenticatable $actor, Product $product): bool
    {
        return $this->owns($actor, $product);
    }

    public function delete(Authenticatable $actor, Product $product): bool
    {
        return $this->owns($actor, $product);
    }

    private function owns(Authenticatable $actor, Product $product): bool
    {
        return ! $actor instanceof Admin && $product->seller->user_id === $actor->getAuthIdentifier();
    }
}
