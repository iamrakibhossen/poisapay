<?php

declare(strict_types=1);

namespace App\Shop\Services;

use App\Models\User;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Seller;
use Illuminate\Support\Facades\Cache;

/**
 * Read-side service for sellers. The "is this user an approved seller?" check runs
 * on every seller-area request, so it's cache-first (Redis). Invalidation is
 * automatic — a Seller `saved`/`deleted` observer (ShopServiceProvider) forgets
 * the key — so there is no manual cache clearing anywhere.
 */
class SellerService
{
    private const TTL = 900; // 15 min

    public static function approvalCacheKey(string $userId): string
    {
        return "shop:seller:approved:{$userId}";
    }

    /** May this user publish/sell? (module enabled + approved). Cache-first. */
    public function isApprovedSeller(User $user): bool
    {
        if (! feature('shop_enabled', false)) {
            return false;
        }

        return Cache::remember(
            self::approvalCacheKey($user->getKey()),
            self::TTL,
            fn (): bool => Seller::query()
                ->where('user_id', $user->getKey())
                ->where('status', SellerStatus::Approved->value)
                ->exists(),
        );
    }

    public function forUser(User $user): ?Seller
    {
        return Seller::query()->where('user_id', $user->getKey())->first();
    }

    public function forget(string $userId): void
    {
        Cache::forget(self::approvalCacheKey($userId));
    }
}
