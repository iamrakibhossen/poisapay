<?php

declare(strict_types=1);

namespace App\Shop\Actions\Seller;

use App\Models\User;
use App\Shop\DTOs\SellerApplicationData;
use App\Shop\Enums\SellerStatus;
use App\Shop\Events\SellerApplied;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Seller;
use App\Shop\Models\SellerApplication;
use Illuminate\Support\Facades\DB;

/**
 * A user applies (or re-applies) to become a seller. Creates/updates the Seller,
 * moves it to pending_review, snapshots the submission into the immutable
 * applications trail, and fires SellerApplied (auto-audited). Idempotent-safe:
 * re-applying is only allowed from draft/rejected.
 */
class SubmitSellerApplication
{
    public function execute(User $user, SellerApplicationData $data): Seller
    {
        if (! feature('shop_enabled', false)) {
            throw ShopException::disabled();
        }

        return DB::transaction(function () use ($user, $data): Seller {
            $seller = Seller::withTrashed()->firstOrNew(['user_id' => $user->getKey()]);

            if ($seller->exists && ! in_array($seller->status, [SellerStatus::Draft, SellerStatus::Rejected], true)) {
                throw ShopException::alreadyApplied($seller->status);
            }

            $seller->fill([
                'brand_name' => $data->brandName,
                'bio' => $data->bio,
                'website' => $data->website,
                'country' => $data->country ?? $user->country ?? null,
                'categories' => $data->categories,
                'settlement_asset_id' => $data->settlementAssetId,
                'status' => SellerStatus::PendingReview,
            ]);
            $seller->deleted_at = null; // un-trash on re-apply (not mass-assignable)
            $seller->save();

            SellerApplication::create([
                'seller_id' => $seller->getKey(),
                'snapshot' => $data->toArray(),
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            SellerApplied::dispatch($seller, $user);

            return $seller;
        });
    }
}
