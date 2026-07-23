<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Card\AuthorizeCardAction;
use App\Domain\Card\CardAuthorizationRequest;
use App\Domain\Card\RefundCardAuthAction;
use App\Domain\Card\SettleCardAuthAction;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardStatus;
use App\Models\Asset;
use App\Models\Card;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Manual test helper: drive a card purchase through the ledger end-to-end
 * (authorize → hold → settle, optional refund) so you can watch the wallet,
 * notification and transaction feed react — no external provider call needed.
 */
class SimulateCardPurchase extends Command
{
    protected $signature = 'card:simulate-purchase
        {card : local Card id or last4}`
        {amount=12.00 : amount in the card settlement currency}
        {--merchant=Test Merchant}
        {--mcc=5812}
        {--activate : activate the card first if it is inactive}
        {--auth-only : place the hold only, do not clear/settle}
        {--refund : refund after settling}';

    protected $description = 'Simulate a card purchase (auth → settle) end-to-end for manual testing.';

    public function handle(AuthorizeCardAction $authorize, SettleCardAuthAction $settle, RefundCardAuthAction $refund, LedgerService $ledger): int
    {
        $key = (string) $this->argument('card');
        $card = Card::with('user')
            ->when(Str::isUuid($key), fn ($q) => $q->where('id', $key), fn ($q) => $q->where('last4', $key))
            ->first();

        if (! $card) {
            $this->error("No card found for [{$key}] (pass a local Card id or last4).");

            return self::FAILURE;
        }
        if ($card->status !== CardStatus::Active) {
            if ($card->status === CardStatus::Inactive && $this->option('activate')) {
                $card->update(['status' => CardStatus::Active]);
                $this->comment('Card activated (local status) for testing.');
            } else {
                $this->error("Card ····{$card->last4} is {$card->status->value} — activate it (Cards page) or pass --activate.");

                return self::FAILURE;
            }
        }

        $minor = (string) (int) round(((float) $this->argument('amount')) * 100);
        $usdt = Asset::where('symbol', 'USDT')->where('is_active', true)->first();
        $balance = fn () => $usdt ? $ledger->availableBalance($card->user_id, $usdt->id)->toDecimal() : 'n/a';

        $this->line("Card ····{$card->last4}  ·  holder {$card->user?->email}");
        $this->line('USDT available before: '.$balance());

        $result = $authorize->authorize(new CardAuthorizationRequest(
            cardRef: $card->issuer_card_ref,
            networkAuthId: 'sim_'.Str::lower(Str::random(20)),
            amountMinor: $minor,
            currency: $card->settlement_currency,
            mcc: (string) $this->option('mcc'),
            merchant: (string) $this->option('merchant'),
        ));

        if (! $result->approved) {
            $this->error("DECLINED: {$result->reason}");
            $this->line('USDT available: '.$balance());

            return self::FAILURE;
        }

        $auth = $result->authorization;
        $this->info("✔ Authorized — hold placed. auth={$auth->id}");
        $this->line('USDT available (held): '.$balance());

        if ($this->option('auth-only')) {
            $this->comment('Auth-only: hold placed, not settled. Reverse it via the webhook pipeline or settle later.');

            return self::SUCCESS;
        }

        $auth = $settle->execute($auth);
        $this->info("✔ Settled — status={$auth->status->value}. Cardholder notification + feed entry dispatched.");

        if ($this->option('refund')) {
            $auth = $refund->execute($auth);
            $this->info("✔ Refunded — status={$auth->status->value}.");
        }

        $this->line('USDT available after: '.$balance());
        $this->newLine();
        $this->line('Now check → /transactions (Cards tab) · /notifications · Admin → Cards.');
        $this->comment('Note: the notification is queued (Redis) — a `php artisan queue:work` must be running to deliver it.');

        return self::SUCCESS;
    }
}
