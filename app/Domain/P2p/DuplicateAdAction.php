<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Compliance\AccountGuard;
use App\Enums\P2pAdStatus;
use App\Models\P2pAd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Clone an existing ad so a merchant can spin up a variant quickly. The copy
 * starts as a Draft with fresh inventory (available = total) and the same rails,
 * so the owner reviews and publishes it deliberately — no funds move at ad time.
 */
class DuplicateAdAction
{
    public function execute(User $user, P2pAd $ad): P2pAd
    {
        if (! feature('p2p_enabled', false)) {
            throw new RuntimeException('P2P marketplace is not enabled.');
        }

        AccountGuard::assertActive($user);

        if ($ad->user_id !== $user->getKey()) {
            throw new RuntimeException('You can only duplicate your own ads.');
        }

        return DB::transaction(function () use ($user, $ad): P2pAd {
            $copy = $ad->replicate(['available_amount', 'deleted_at', 'created_at', 'updated_at']);
            $copy->available_amount = $ad->total_amount;   // fresh inventory
            $copy->status = P2pAdStatus::Draft;            // review before it goes live
            $copy->save();

            $copy->paymentMethods()->sync($ad->paymentMethods()->pluck('p2p_payment_methods.id')->all());

            ActivityLogger::log('p2p.ad.duplicated', $copy, ['source_ad' => $ad->id], actor: $user);

            return $copy;
        });
    }
}
