<?php

declare(strict_types=1);

namespace App\Domain\Transaction;

use App\Domain\Card\CardPricing;
use App\Enums\CardAuthStatus;
use App\Enums\LedgerSide;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\Conversion;
use App\Models\Deposit;
use App\Models\JournalEntry;
use App\Models\MerchantInvoice;
use App\Models\P2pOrder;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Unified activity timeline (deposits, withdrawals, transfers, swaps, payments)
 * for a user. Extracted from the old Livewire Transactions component so the JSON
 * frontend API and any other consumer share one source of truth.
 */
class TransactionFeedService
{
    private const SOURCE_LIMIT = 200;

    /**
     * @param  array{type?: string, asset?: string, search?: string}  $filters
     * @return array{items: array, total: int, month_count: int, symbols: array<int, string>, page: int, per_page: int, last_page: int}
     */
    public function feed(User $user, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $all = $this->activity($user->id);
        $symbols = $all->pluck('asset')->filter()->unique()->sort()->values();
        $monthCount = $all->filter(fn (array $i) => $i['_at']->isCurrentMonth())->count();

        $type = $filters['type'] ?? 'all';
        $asset = $filters['asset'] ?? 'all';
        $search = trim($filters['search'] ?? '');

        $filtered = $all
            ->when($type !== 'all', fn (Collection $c) => $c->where('group', $type))
            ->when($asset !== 'all', fn (Collection $c) => $c->where('asset', $asset))
            ->when($search !== '', function (Collection $c) use ($search) {
                $needle = mb_strtolower($search);

                return $c->filter(fn (array $i) => str_contains(mb_strtolower($i['title']), $needle)
                    || str_contains(mb_strtolower($i['status']), $needle)
                    || str_contains(mb_strtolower((string) $i['subtitle']), $needle));
            })
            ->values();

        $total = $filtered->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        $items = $filtered->forPage($page, $perPage)->map(function (array $i) {
            unset($i['_at']);

            return $i;
        })->values()->all();

        return [
            'items' => $items,
            'total' => $total,
            'month_count' => $monthCount,
            'symbols' => $symbols->all(),
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    private function activity(string $userId): Collection
    {
        return $this->deposits($userId)
            ->concat($this->withdrawals($userId))
            ->concat($this->transfers($userId))
            ->concat($this->conversions($userId))
            ->concat($this->payments($userId))
            ->concat($this->p2p($userId))
            ->concat($this->cards($userId))
            ->concat($this->cardIssuance($userId))
            ->sortByDesc('_at')
            ->values();
    }

    private function row(array $data, Carbon $at): array
    {
        // Every row links to the unified detail page; the old per-source url is
        // preserved as a "related" link shown inside that page.
        if (isset($data['source'], $data['id'])) {
            $data['related_url'] = $data['url'] ?? null;
            $data['url'] = route('transactions.show', ['source' => $data['source'], 'id' => $data['id']]);
        }

        return array_merge($data, [
            '_at' => $at,
            'at' => $at->toIso8601String(),
            'at_human' => $at->diffForHumans(),
        ]);
    }

    private function deposits(string $userId): Collection
    {
        return Deposit::with('asset')->where('user_id', $userId)->latest()->limit(self::SOURCE_LIMIT)->get()
            ->map(fn (Deposit $d) => $this->row([
                'group' => 'deposits', 'type' => 'Deposit', 'icon' => 'arrow-down-left', 'color' => 'success',
                'title' => 'Deposit', 'subtitle' => $d->asset->symbol, 'amount' => '+'.$d->money()->format(),
                'status' => $d->status->label(), 'statusColor' => $d->status->color(),
                'asset' => $d->asset->symbol, 'source' => 'deposit', 'id' => $d->id, 'url' => route('wallet.show', $d->asset->symbol),
            ], $d->created_at));
    }

    private function withdrawals(string $userId): Collection
    {
        return Withdrawal::with('asset')->where('user_id', $userId)->latest()->limit(self::SOURCE_LIMIT)->get()
            ->map(fn (Withdrawal $w) => $this->row([
                'group' => 'withdrawals', 'type' => 'Withdrawal', 'icon' => 'arrow-up-right', 'color' => 'warning',
                'title' => 'Withdrawal', 'subtitle' => $w->to_address ? 'To '.$this->shorten($w->to_address) : $w->asset->symbol,
                'amount' => '-'.$w->money()->format(), 'status' => $w->status->label(), 'statusColor' => $w->status->color(),
                'asset' => $w->asset->symbol, 'source' => 'withdrawal', 'id' => $w->id, 'url' => route('wallet.show', $w->asset->symbol),
            ], $w->created_at));
    }

    /**
     * P2P trades the user was party to — including admin dispute rulings, which
     * settle the escrow on the ledger (buyer receives crypto / seller is refunded)
     * but otherwise leave no trace in this feed. Buyer "buys" (receives net),
     * seller "sells" (releases the gross crypto amount).
     */
    private function p2p(string $userId): Collection
    {
        return P2pOrder::with('asset')
            ->where(fn ($q) => $q->where('buyer_id', $userId)->orWhere('seller_id', $userId))
            ->latest()->limit(self::SOURCE_LIMIT)->get()
            ->map(function (P2pOrder $o) use ($userId) {
                $isBuyer = $o->buyer_id === $userId;
                $money = $isBuyer ? $o->netMoney() : $o->cryptoMoney();

                return $this->row([
                    'group' => 'p2p', 'type' => 'P2P',
                    'icon' => $isBuyer ? 'arrow-down-left' : 'arrow-up-right',
                    'color' => $isBuyer ? 'success' : 'info',
                    'title' => ($isBuyer ? 'Bought ' : 'Sold ').$o->asset->symbol,
                    'subtitle' => 'P2P · '.$o->ref,
                    'amount' => ($isBuyer ? '+' : '-').$money->format(),
                    'status' => str($o->status->value)->headline()->toString(),
                    'statusColor' => $o->status->color(),
                    'asset' => $o->asset->symbol, 'source' => 'p2p', 'id' => $o->id, 'url' => route('p2p.order', $o),
                ], $o->created_at);
            });
    }

    private function transfers(string $userId): Collection
    {
        return Transfer::with('asset')
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->latest()->limit(self::SOURCE_LIMIT)->get()
            ->map(function (Transfer $t) use ($userId) {
                $sent = $t->sender_id === $userId;

                return $this->row([
                    'group' => 'transfers', 'type' => $sent ? 'Sent' : 'Received',
                    'icon' => $sent ? 'arrow-up-right' : 'arrow-down-left', 'color' => $sent ? 'info' : 'success',
                    'title' => $sent ? 'Sent '.$t->asset->symbol : 'Received '.$t->asset->symbol,
                    'subtitle' => $t->memo ?: $t->kind->label(), 'amount' => ($sent ? '-' : '+').$t->money()->format(),
                    'status' => $t->status->label(), 'statusColor' => $t->status->color(),
                    'asset' => $t->asset->symbol, 'source' => 'transfer', 'id' => $t->id, 'url' => route('wallet.show', $t->asset->symbol),
                ], $t->created_at);
            });
    }

    private function conversions(string $userId): Collection
    {
        return Conversion::with('quote.fromAsset', 'quote.toAsset')->where('user_id', $userId)
            ->latest()->limit(self::SOURCE_LIMIT)->get()
            ->filter(fn (Conversion $c) => $c->quote && $c->quote->fromAsset && $c->quote->toAsset)
            ->map(function (Conversion $c) {
                $from = $c->quote->fromAsset;
                $to = $c->quote->toAsset;
                $fromMoney = Money::ofBase((string) $c->quote->from_amount, $from->decimals, $from->symbol)->format();
                $toMoney = Money::ofBase((string) $c->quote->to_amount, $to->decimals, $to->symbol)->format();

                return $this->row([
                    'group' => 'swaps', 'type' => 'Swap', 'icon' => 'arrows-right-left', 'color' => 'primary',
                    'title' => $from->symbol.' → '.$to->symbol, 'subtitle' => $fromMoney.' → '.$toMoney,
                    'amount' => '+'.$toMoney, 'status' => 'Completed', 'statusColor' => 'success',
                    'asset' => $to->symbol, 'source' => 'swap', 'id' => $c->id, 'url' => route('wallet.show', $to->symbol),
                ], $c->created_at);
            })->values();
    }

    private function payments(string $userId): Collection
    {
        return MerchantInvoice::with('asset')->where('status', 'paid')
            ->where(fn ($q) => $q->where('payer_id', $userId)->orWhere('merchant_id', $userId))
            ->latest()->limit(self::SOURCE_LIMIT)->get()
            ->map(function (MerchantInvoice $i) use ($userId) {
                $paid = $i->payer_id === $userId;

                return $this->row([
                    'group' => 'payments', 'type' => 'Payment', 'icon' => $paid ? 'arrow-up-right' : 'arrow-down-left',
                    'color' => $paid ? 'warning' : 'success', 'title' => $paid ? 'Payment sent' : 'Payment received',
                    'subtitle' => $i->reference ?: $i->asset->symbol, 'amount' => ($paid ? '-' : '+').$i->money()->format(),
                    'status' => 'Paid', 'statusColor' => 'success',
                    'asset' => $i->asset->symbol, 'source' => 'invoice', 'id' => $i->id, 'url' => route('wallet.show', $i->asset->symbol),
                ], $i->paid_at ?? $i->created_at);
            });
    }

    private function cards(string $userId): Collection
    {
        return CardAuthorization::with('card')
            ->whereHas('card', fn ($q) => $q->where('user_id', $userId))
            ->whereIn('status', [CardAuthStatus::Approved, CardAuthStatus::Settled, CardAuthStatus::Reversed])
            ->latest()->limit(self::SOURCE_LIMIT)->get()
            ->map(function (CardAuthorization $a) {
                $back = $a->status === CardAuthStatus::Reversed;   // refund/reversal = money back
                $amount = number_format((int) $a->amount / 100, 2);

                return $this->row([
                    'group' => 'cards', 'type' => 'Card',
                    'icon' => 'credit-card', 'color' => $back ? 'success' : 'info',
                    'title' => $a->merchant ?: 'Card payment',
                    'subtitle' => $a->card?->displayName() ?? 'Card',
                    'amount' => ($back ? '+' : '-').$a->currency_code.' '.$amount,
                    'status' => $a->status->label(), 'statusColor' => $a->status->color(),
                    'asset' => $a->currency_code, 'source' => 'card', 'id' => $a->id, 'url' => route('cards.manage', $a->card_id),
                ], $a->created_at);
            });
    }

    /** One-time card issuance fees — booked to the ledger (card.issue.fee), no domain model. */
    private function cardIssuance(string $userId): Collection
    {
        $cardIds = Card::where('user_id', $userId)->pluck('id');
        if ($cardIds->isEmpty()) {
            return collect();
        }

        return JournalEntry::with('lines.asset')
            ->where('type', 'card.issue.fee')
            ->whereIn('metadata->card_id', $cardIds->all())
            ->latest('posted_at')->limit(self::SOURCE_LIMIT)->get()
            ->map(function (JournalEntry $e) {
                // The debit line is the user's charge (user:available -> fee:card).
                $debit = $e->lines->firstWhere('side', LedgerSide::Debit);
                if (! $debit || ! $debit->asset) {
                    return null;
                }
                $paid = Money::ofBase($debit->amount, $debit->asset->decimals, $debit->asset->symbol);

                // The card is PRICED in USD (metadata price_minor, 2dp) — show that as the
                // amount. It settled in a funding asset (e.g. USDT); when they differ, note
                // the funding asset in the subtitle so the receipt stays transparent.
                $meta = is_array($e->metadata) ? $e->metadata : [];
                $priceMinor = (int) ($meta['price_minor'] ?? 0);
                $currency = CardPricing::currency();
                $amount = $priceMinor > 0 ? number_format($priceMinor / 100, 2).' '.$currency : $paid->format();
                $subtitle = $priceMinor > 0 && $debit->asset->symbol !== $currency
                    ? 'Card issuance fee · paid in '.$paid->format()
                    : 'Card issuance fee';

                return $this->row([
                    'group' => 'cards', 'type' => 'Card',
                    'icon' => 'credit-card', 'color' => 'warning',
                    'title' => 'Card purchase', 'subtitle' => $subtitle,
                    'amount' => '-'.$amount, 'status' => 'Completed', 'statusColor' => 'success',
                    'asset' => $currency, 'source' => 'card_issue', 'id' => $e->id, 'url' => route('cards'),
                ], $e->posted_at ?? $e->created_at);
            })->filter()->values();
    }

    /**
     * Full detail for one transaction, resolved by its source + id and scoped to
     * the owner. Returns null when it does not exist or is not the user's.
     *
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $source, string $id): ?array
    {
        return match ($source) {
            'deposit' => $this->depositDetail($user, $id),
            'withdrawal' => $this->withdrawalDetail($user, $id),
            'transfer' => $this->transferDetail($user, $id),
            'swap' => $this->swapDetail($user, $id),
            'invoice' => $this->invoiceDetail($user, $id),
            'p2p' => $this->p2pDetail($user, $id),
            'card' => $this->cardDetail($user, $id),
            'card_issue' => $this->cardIssueDetail($user, $id),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailArray(array $data, Carbon $at): array
    {
        return array_merge(['rows' => [], 'related' => []], $data, [
            'at' => $at,
            'at_full' => $at->format('M j, Y · g:i A'),
            'at_human' => $at->diffForHumans(),
        ]);
    }

    /** @return array{label: string, value: string, mono: bool}|null */
    private function kv(string $label, ?string $value, bool $mono = false): ?array
    {
        return ($value === null || $value === '') ? null : ['label' => $label, 'value' => $value, 'mono' => $mono];
    }

    private function userName(?string $id): ?string
    {
        return $id ? User::whereKey($id)->value('name') : null;
    }

    /** @return array<string, mixed>|null */
    private function depositDetail(User $user, string $id): ?array
    {
        $d = Deposit::with('asset.chain')->where('user_id', $user->id)->find($id);
        if (! $d) {
            return null;
        }

        return $this->detailArray([
            'title' => 'Deposit', 'type' => 'Deposit', 'group' => 'deposits',
            'icon' => 'arrow-down-left', 'color' => 'success', 'direction' => '+',
            'amount' => '+'.$d->money()->format(), 'status' => $d->status->label(), 'statusColor' => $d->status->color(),
            'asset' => $d->asset->symbol,
            'rows' => array_values(array_filter([
                $this->kv('Asset', $d->asset->symbol),
                $this->kv('Network', $d->asset->chain?->name),
                $this->kv('Amount', $d->money()->format()),
                $this->kv('Fee', $d->feeMoney()->isPositive() ? $d->feeMoney()->format() : null),
                $this->kv('Net credited', $d->netMoney()->format()),
                $this->kv('Confirmations', $d->confirmations.' / '.$d->required_confirmations),
                $this->kv('On-chain tx', $d->onchain_tx_id, true),
                $this->kv('Reference', $d->reference, true),
                $this->kv('Requested', $d->created_at?->format('M j, Y · g:i A')),
                $this->kv('Credited', $d->credited_at?->format('M j, Y · g:i A')),
            ])),
            'related' => [['label' => 'View '.$d->asset->symbol.' wallet', 'url' => route('wallet.show', $d->asset->symbol)]],
        ], $d->credited_at ?? $d->created_at);
    }

    /** @return array<string, mixed>|null */
    private function withdrawalDetail(User $user, string $id): ?array
    {
        $w = Withdrawal::with('asset.chain')->where('user_id', $user->id)->find($id);
        if (! $w) {
            return null;
        }

        $related = [['label' => 'View '.$w->asset->symbol.' wallet', 'url' => route('wallet.show', $w->asset->symbol)]];
        if ($w->conversion_id) {
            $related[] = ['label' => 'View conversion', 'url' => route('transactions.show', ['source' => 'swap', 'id' => $w->conversion_id])];
        }

        return $this->detailArray([
            'title' => 'Withdrawal', 'type' => 'Withdrawal', 'group' => 'withdrawals',
            'icon' => 'arrow-up-right', 'color' => 'warning', 'direction' => '-',
            'amount' => '-'.$w->money()->format(), 'status' => $w->status->label(), 'statusColor' => $w->status->color(),
            'asset' => $w->asset->symbol,
            'rows' => array_values(array_filter([
                $this->kv('Asset', $w->asset->symbol),
                $this->kv('Network', $w->asset->chain?->name),
                $this->kv($w->isFiatPayout() ? 'Payout to' : 'To address', $w->to_address, true),
                $this->kv('Payout method', $w->payout_method ? ucfirst((string) $w->payout_method) : null),
                $this->kv('Amount', $w->money()->format()),
                $this->kv('Fee', $w->feeMoney()->isPositive() ? $w->feeMoney()->format() : null),
                $this->kv('On-chain tx', $w->onchain_tx_id, true),
                $this->kv('Risk', $w->risk_level ? ucfirst($w->risk_level->value).' ('.$w->risk_score.')' : null),
                $this->kv('Failure reason', $w->failure_reason),
                $this->kv('Reference', $w->idempotency_key, true),
                $this->kv('Requested', $w->created_at?->format('M j, Y · g:i A')),
                $this->kv('Completed', $w->completed_at?->format('M j, Y · g:i A')),
            ])),
            'related' => $related,
        ], $w->created_at);
    }

    /** @return array<string, mixed>|null */
    private function transferDetail(User $user, string $id): ?array
    {
        $t = Transfer::with('asset')
            ->where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))
            ->find($id);
        if (! $t) {
            return null;
        }
        $sent = $t->sender_id === $user->id;
        $party = $sent
            ? ($this->userName($t->recipient_id) ?? $t->recipient_handle)
            : $this->userName($t->sender_id);

        return $this->detailArray([
            'title' => $sent ? 'Sent '.$t->asset->symbol : 'Received '.$t->asset->symbol,
            'type' => $sent ? 'Sent' : 'Received', 'group' => 'transfers',
            'icon' => $sent ? 'arrow-up-right' : 'arrow-down-left', 'color' => $sent ? 'info' : 'success',
            'direction' => $sent ? '-' : '+',
            'amount' => ($sent ? '-' : '+').$t->money()->format(), 'status' => $t->status->label(), 'statusColor' => $t->status->color(),
            'asset' => $t->asset->symbol,
            'rows' => array_values(array_filter([
                $this->kv('Asset', $t->asset->symbol),
                $this->kv($sent ? 'To' : 'From', $party),
                $this->kv('Type', $t->kind->label()),
                $this->kv('Amount', $t->money()->format()),
                $this->kv('Memo', $t->memo),
                $this->kv('Reference', $t->idempotency_key, true),
                $this->kv('Date', $t->created_at?->format('M j, Y · g:i A')),
            ])),
            'related' => [['label' => 'View '.$t->asset->symbol.' wallet', 'url' => route('wallet.show', $t->asset->symbol)]],
        ], $t->created_at);
    }

    /** @return array<string, mixed>|null */
    private function swapDetail(User $user, string $id): ?array
    {
        $c = Conversion::with('quote.fromAsset', 'quote.toAsset')->where('user_id', $user->id)->find($id);
        if (! $c || ! $c->quote || ! $c->quote->fromAsset || ! $c->quote->toAsset) {
            return null;
        }
        $from = $c->quote->fromAsset;
        $to = $c->quote->toAsset;
        $fromMoney = Money::ofBase((string) $c->quote->from_amount, $from->decimals, $from->symbol);
        $toMoney = Money::ofBase((string) $c->quote->to_amount, $to->decimals, $to->symbol);

        return $this->detailArray([
            'title' => $from->symbol.' → '.$to->symbol, 'type' => 'Swap', 'group' => 'swaps',
            'icon' => 'arrows-right-left', 'color' => 'primary', 'direction' => '+',
            'amount' => '+'.$toMoney->format(), 'status' => 'Completed', 'statusColor' => 'success',
            'asset' => $to->symbol,
            'rows' => array_values(array_filter([
                $this->kv('You paid', $fromMoney->format()),
                $this->kv('You received', $toMoney->format()),
                $this->kv('Rate', '1 '.$from->symbol.' = '.rtrim((string) $c->quote->rate, '0').' '.$to->symbol),
                $this->kv('Spread', Money::ofBase((string) $c->spread_amount, $from->decimals, $from->symbol)->format().' ('.($c->quote->spread_bps / 100).'%)'),
                $this->kv('Fee', $c->fee_amount && (string) $c->fee_amount !== '0' ? Money::ofBase((string) $c->fee_amount, $from->decimals, $from->symbol)->format() : null),
                $this->kv('Context', ucfirst($c->context->value)),
                $this->kv('Reference', $c->idempotency_key, true),
                $this->kv('Completed', ($c->completed_at ?? $c->created_at)?->format('M j, Y · g:i A')),
            ])),
            'related' => [['label' => 'View '.$to->symbol.' wallet', 'url' => route('wallet.show', $to->symbol)]],
        ], $c->completed_at ?? $c->created_at);
    }

    /** @return array<string, mixed>|null */
    private function invoiceDetail(User $user, string $id): ?array
    {
        $i = MerchantInvoice::with('asset')
            ->where(fn ($q) => $q->where('payer_id', $user->id)->orWhere('merchant_id', $user->id))
            ->find($id);
        if (! $i) {
            return null;
        }
        $paid = $i->payer_id === $user->id;

        return $this->detailArray([
            'title' => $paid ? 'Payment sent' : 'Payment received', 'type' => 'Payment', 'group' => 'payments',
            'icon' => $paid ? 'arrow-up-right' : 'arrow-down-left', 'color' => $paid ? 'warning' : 'success',
            'direction' => $paid ? '-' : '+',
            'amount' => ($paid ? '-' : '+').$i->money()->format(), 'status' => ucfirst((string) $i->status), 'statusColor' => 'success',
            'asset' => $i->asset->symbol,
            'rows' => array_values(array_filter([
                $this->kv('Reference', $i->reference, true),
                $this->kv($paid ? 'Paid to' : 'From', $this->userName($paid ? $i->merchant_id : $i->payer_id)),
                $this->kv('Amount', $i->money()->format()),
                $this->kv('Fee', $i->feeMoney()->isPositive() ? $i->feeMoney()->format() : null),
                $this->kv('Net', $i->netMoney()->format()),
                $this->kv('Memo', $i->memo),
                $this->kv('Paid at', $i->paid_at?->format('M j, Y · g:i A')),
            ])),
            'related' => [['label' => 'View '.$i->asset->symbol.' wallet', 'url' => route('wallet.show', $i->asset->symbol)]],
        ], $i->paid_at ?? $i->created_at);
    }

    /** @return array<string, mixed>|null */
    private function p2pDetail(User $user, string $id): ?array
    {
        $o = P2pOrder::with('asset')
            ->where(fn ($q) => $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
            ->find($id);
        if (! $o) {
            return null;
        }
        $isBuyer = $o->buyer_id === $user->id;

        return $this->detailArray([
            'title' => ($isBuyer ? 'Bought ' : 'Sold ').$o->asset->symbol, 'type' => 'P2P', 'group' => 'p2p',
            'icon' => $isBuyer ? 'arrow-down-left' : 'arrow-up-right', 'color' => $isBuyer ? 'success' : 'info',
            'direction' => $isBuyer ? '+' : '-',
            'amount' => ($isBuyer ? '+' : '-').($isBuyer ? $o->netMoney() : $o->cryptoMoney())->format(),
            'status' => str($o->status->value)->headline()->toString(), 'statusColor' => $o->status->color(),
            'asset' => $o->asset->symbol,
            'rows' => array_values(array_filter([
                $this->kv('Reference', $o->ref, true),
                $this->kv('Side', $isBuyer ? 'Buy' : 'Sell'),
                $this->kv('Crypto amount', $o->cryptoMoney()->format()),
                $this->kv('Fiat amount', $o->fiat_amount ? number_format((float) $o->fiat_amount, 2).' '.$o->fiat_currency : null),
                $this->kv('Price', $o->price ? number_format((float) $o->price, 2).' '.$o->fiat_currency : null),
                $this->kv('Fee', $o->feeMoney()->isPositive() ? $o->feeMoney()->format() : null),
                $this->kv('Net', $o->netMoney()->format()),
                $this->kv($isBuyer ? 'Seller' : 'Buyer', $this->userName($isBuyer ? $o->seller_id : $o->buyer_id)),
                $this->kv('Buyer paid at', $o->buyer_paid_at?->format('M j, Y · g:i A')),
                $this->kv('Released at', $o->released_at?->format('M j, Y · g:i A')),
                $this->kv('Cancel reason', $o->cancel_reason),
            ])),
            'related' => [['label' => 'View trade', 'url' => route('p2p.order', $o)]],
        ], $o->created_at);
    }

    /** @return array<string, mixed>|null */
    private function cardDetail(User $user, string $id): ?array
    {
        $a = CardAuthorization::with('card')
            ->whereHas('card', fn ($q) => $q->where('user_id', $user->id))
            ->find($id);
        if (! $a) {
            return null;
        }
        $back = $a->status === CardAuthStatus::Reversed;
        $amount = number_format((int) $a->amount / 100, 2);

        return $this->detailArray([
            'title' => $a->merchant ?: 'Card payment', 'type' => 'Card', 'group' => 'cards',
            'icon' => 'credit-card', 'color' => $back ? 'success' : 'info', 'direction' => $back ? '+' : '-',
            'amount' => ($back ? '+' : '-').$a->currency_code.' '.$amount,
            'status' => $a->status->label(), 'statusColor' => $a->status->color(),
            'asset' => $a->currency_code,
            'rows' => array_values(array_filter([
                $this->kv('Merchant', $a->merchant),
                $this->kv('Card', $a->card?->displayName()),
                $this->kv('Amount', $a->currency_code.' '.$amount),
                $this->kv('Channel', $a->channel ? ucfirst((string) $a->channel) : null),
                $this->kv('MCC', $a->mcc),
                $this->kv('Funding source', $a->fundingSourceLabel()),
                $this->kv('Exchange rate', $a->exchangeRateLabel()),
                $this->kv('Status', $a->status->label()),
                $this->kv('Auth ID', $a->network_auth_id, true),
                $this->kv('Settled at', $a->settled_at?->format('M j, Y · g:i A')),
                $this->kv('Date', $a->created_at?->format('M j, Y · g:i A')),
            ])),
            'related' => $a->card_id ? [['label' => 'Manage card', 'url' => route('cards.manage', $a->card_id)]] : [],
        ], $a->created_at);
    }

    /** @return array<string, mixed>|null */
    private function cardIssueDetail(User $user, string $id): ?array
    {
        $e = JournalEntry::with('lines.asset')->where('type', 'card.issue.fee')->find($id);
        $meta = is_array($e?->metadata) ? $e->metadata : [];
        $cardId = $meta['card_id'] ?? null;
        $card = $cardId ? Card::where('user_id', $user->id)->find($cardId) : null;
        if (! $e || ! $card) {
            return null;
        }
        $debit = $e->lines->firstWhere('side', LedgerSide::Debit);
        $paid = $debit && $debit->asset ? Money::ofBase($debit->amount, $debit->asset->decimals, $debit->asset->symbol) : null;
        $priceMinor = (int) ($meta['price_minor'] ?? 0);
        $currency = CardPricing::currency();
        $price = $priceMinor > 0 ? number_format($priceMinor / 100, 2).' '.$currency : $paid?->format();

        return $this->detailArray([
            'title' => 'Card purchase', 'type' => 'Card', 'group' => 'cards',
            'icon' => 'credit-card', 'color' => 'warning', 'direction' => '-',
            'amount' => '-'.$price, 'status' => 'Completed', 'statusColor' => 'success',
            'asset' => $currency,
            'rows' => array_values(array_filter([
                $this->kv('Card', $card->displayName()),
                $this->kv('Type', $card->type->label()),
                $this->kv('Price', $price),
                $this->kv('Paid', $paid && $debit->asset->symbol !== $currency ? $paid->format() : null),
                $this->kv('Date', ($e->posted_at ?? $e->created_at)?->format('M j, Y · g:i A')),
            ])),
            'related' => [['label' => 'View cards', 'url' => route('cards')]],
        ], $e->posted_at ?? $e->created_at);
    }

    private function shorten(string $address): string
    {
        return mb_strlen($address) > 14 ? mb_substr($address, 0, 6).'…'.mb_substr($address, -4) : $address;
    }
}
