<?php

declare(strict_types=1);

namespace App\Shop\Policies;

use App\Models\Admin;
use App\Shop\Models\ShopMedia;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Media is merchant-owned: only the seller who uploaded an asset may view, edit,
 * or delete it. Operators (Admin) may view for support/moderation.
 */
class MediaPolicy
{
    public function view(Authenticatable $actor, ShopMedia $media): bool
    {
        return $this->owns($actor, $media) || $actor instanceof Admin;
    }

    public function update(Authenticatable $actor, ShopMedia $media): bool
    {
        return $this->owns($actor, $media);
    }

    public function delete(Authenticatable $actor, ShopMedia $media): bool
    {
        return $this->owns($actor, $media);
    }

    private function owns(Authenticatable $actor, ShopMedia $media): bool
    {
        return ! $actor instanceof Admin && $media->seller->user_id === $actor->getAuthIdentifier();
    }
}
