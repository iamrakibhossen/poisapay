<?php

declare(strict_types=1);

namespace App\Shop\Actions\Seller;

use App\Models\Admin;
use App\Notifications\OperatorNotification;
use App\Shop\Enums\SellerStatus;
use App\Shop\Events\SellerStatusChanged;
use App\Shop\Models\Seller;
use Illuminate\Support\Facades\DB;

/**
 * Operator control over a seller's lifecycle: approve / reject / suspend /
 * reinstate. Records the decision on the latest application, fires
 * SellerStatusChanged (auto-audited + drives welcome/provision listeners), and
 * notifies the seller via the core Notification module.
 */
class SetSellerStatus
{
    public function execute(Seller $seller, SellerStatus $to, ?Admin $operator = null, ?string $reason = null): Seller
    {
        $from = $seller->status;

        return DB::transaction(function () use ($seller, $from, $to, $operator, $reason): Seller {
            $seller->update([
                'status' => $to,
                'reviewed_by' => $operator?->getKey(),
                'reviewed_at' => now(),
                'approved_at' => $to === SellerStatus::Approved && ! $seller->approved_at ? now() : $seller->approved_at,
            ]);

            // Reflect approve/reject on the latest pending application row.
            $decision = match ($to) {
                SellerStatus::Approved => 'approved',
                SellerStatus::Rejected => 'rejected',
                default => null,
            };
            if ($decision) {
                $seller->applications()->where('status', 'pending')->latest('submitted_at')->first()?->update([
                    'status' => $decision,
                    'decided_by' => $operator?->getKey(),
                    'decided_at' => now(),
                    'notes' => $reason,
                ]);
            }

            SellerStatusChanged::dispatch($seller, $from, $to, $operator, $reason);

            $seller->user?->notify(new OperatorNotification(
                title: 'Seller account updated',
                body: "Your seller account is now {$to->label()}.".($reason ? " Reason: {$reason}" : ''),
                category: 'seller',
            ));

            return $seller->refresh();
        });
    }
}
