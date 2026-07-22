<?php

declare(strict_types=1);

namespace App\Domain\Transaction;

use App\Enums\CardAuthStatus;
use App\Models\CardAuthorization;
use App\Models\Conversion;
use App\Models\Deposit;
use App\Models\MerchantInvoice;
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
            ->concat($this->cards($userId))
            ->sortByDesc('_at')
            ->values();
    }

    private function row(array $data, Carbon $at): array
    {
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
                'asset' => $d->asset->symbol, 'url' => route('wallet.show', $d->asset->symbol),
            ], $d->created_at));
    }

    private function withdrawals(string $userId): Collection
    {
        return Withdrawal::with('asset')->where('user_id', $userId)->latest()->limit(self::SOURCE_LIMIT)->get()
            ->map(fn (Withdrawal $w) => $this->row([
                'group' => 'withdrawals', 'type' => 'Withdrawal', 'icon' => 'arrow-up-right', 'color' => 'warning',
                'title' => 'Withdrawal', 'subtitle' => $w->to_address ? 'To '.$this->shorten($w->to_address) : $w->asset->symbol,
                'amount' => '-'.$w->money()->format(), 'status' => $w->status->label(), 'statusColor' => $w->status->color(),
                'asset' => $w->asset->symbol, 'url' => route('wallet.show', $w->asset->symbol),
            ], $w->created_at));
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
                    'asset' => $t->asset->symbol, 'url' => route('wallet.show', $t->asset->symbol),
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
                $fromMoney = Money::ofBase($c->quote->from_amount, $from->decimals, $from->symbol)->format();
                $toMoney = Money::ofBase($c->quote->to_amount, $to->decimals, $to->symbol)->format();

                return $this->row([
                    'group' => 'swaps', 'type' => 'Swap', 'icon' => 'arrows-right-left', 'color' => 'primary',
                    'title' => $from->symbol.' → '.$to->symbol, 'subtitle' => $fromMoney.' → '.$toMoney,
                    'amount' => '+'.$toMoney, 'status' => 'Completed', 'statusColor' => 'success',
                    'asset' => $to->symbol, 'url' => route('wallet.show', $to->symbol),
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
                    'asset' => $i->asset->symbol, 'url' => route('wallet.show', $i->asset->symbol),
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
                    'asset' => $a->currency_code, 'url' => route('cards.manage', $a->card_id),
                ], $a->created_at);
            });
    }

    private function shorten(string $address): string
    {
        return mb_strlen($address) > 14 ? mb_substr($address, 0, 6).'…'.mb_substr($address, -4) : $address;
    }
}
