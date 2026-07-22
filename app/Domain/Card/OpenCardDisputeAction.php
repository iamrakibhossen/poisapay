<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Audit\ActivityLogger;
use App\Enums\CardAuthStatus;
use App\Models\CardAuthorization;
use App\Models\CardDispute;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cardholder-initiated dispute against a settled purchase (TDD §F3.6). Opens a
 * chargeback case; the money moves only on resolution. One open dispute per auth.
 */
class OpenCardDisputeAction
{
    public function execute(CardAuthorization $auth, string $reason): CardDispute
    {
        return DB::transaction(function () use ($auth, $reason): CardDispute {
            if ($auth->status !== CardAuthStatus::Settled) {
                throw new RuntimeException('Only a settled purchase can be disputed.');
            }
            if (CardDispute::where('authorization_id', $auth->id)->whereIn('status', ['open', 'represented'])->exists()) {
                throw new RuntimeException('This purchase already has an open dispute.');
            }

            $dispute = CardDispute::create([
                'authorization_id' => $auth->id,
                'reason' => $reason,
                'status' => 'open',
                'amount' => $auth->amount,
            ]);

            ActivityLogger::log('card.dispute.opened', $dispute, [
                'authorization_id' => $auth->id,
                'reason' => $reason,
            ]);

            notifyAdmins(
                'Card dispute opened',
                "{$auth->card?->user?->name} disputed a {$auth->merchant} charge — reason: {$reason}.",
                route('admin.card-disputes'),
                'card',
            );

            return $dispute;
        });
    }
}
