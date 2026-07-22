<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardAuthStatus;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\CardAuthorization;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Merchant refund of a settled card purchase (TDD §F3.4). Crypto flows back
 * treasury:hot -> user:available at the funding asset's current terms. A full
 * or partial refund is supported; the auth's status flips to Reversed only on
 * a full refund. Idempotent by the refund reference.
 */
class RefundCardAuthAction
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(CardAuthorization $auth, ?Money $refundAmount = null, string $reference = 'full'): CardAuthorization
    {
        return DB::transaction(function () use ($auth, $refundAmount, $reference): CardAuthorization {
            $auth = CardAuthorization::whereKey($auth->id)->lockForUpdate()->firstOrFail();
            if ($auth->status !== CardAuthStatus::Settled) {
                throw new RuntimeException('Only a settled authorisation can be refunded.');
            }

            $asset = Asset::findOrFail($auth->funding_asset_id);
            $settled = Money::ofBase($auth->held_amount, $asset->decimals, $asset->symbol);
            $refund = $refundAmount ?? $settled;
            if ($refund->isGreaterThanOrEqual($settled) && ! $refund->equals($settled)) {
                throw new RuntimeException('Refund cannot exceed the settled amount.');
            }
            if (! $refund->isPositive()) {
                throw new RuntimeException('Refund amount must be positive.');
            }

            $available = $this->accounts->forUser($auth->card->user_id, LedgerAccountType::UserAvailable, $asset->id);
            $settlement = $this->accounts->system(LedgerAccountType::CardProgramSettlement, $asset->id);

            $entry = $this->ledger->post(new EntryData(
                type: 'card.refund',
                idempotencyKey: "card:refund:{$auth->network_auth_id}:{$reference}",
                lines: [
                    PostingLine::debit($settlement->id, $asset->id, $refund),
                    PostingLine::credit($available->id, $asset->id, $refund),
                ],
                memo: "Card refund {$auth->merchant}",
                metadata: ['authorization_id' => $auth->id, 'reference' => $reference],
            ));

            if ($refund->equals($settled)) {
                $auth->update(['status' => CardAuthStatus::Reversed]);
            }

            ActivityLogger::log('card.refunded', $auth, [
                'merchant' => $auth->merchant,
                'refund_base' => $refund->baseString(),
                'reference' => $reference,
            ]);

            return $auth->refresh();
        });
    }
}
