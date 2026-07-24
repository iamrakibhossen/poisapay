<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Wallet\WalletService;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\Deposit;
use App\Models\Transfer;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Support\BaseCurrency;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Per-asset detail page — server-rendered. The {@see $asset} route segment is
 * the asset SYMBOL; the controller resolves it, builds the balance + activity
 * list and passes them straight to the Blade view. No JSON API.
 */
class AssetShowController extends Controller
{
    public function index(Request $request, string $asset, WalletService $wallets): View
    {
        // A coin can settle on several chains but the wallet is the COIN: the
        // balance is pooled, so read it once from the canonical network.
        $models = Asset::with('chain')->where('symbol', $asset)->where('is_active', true)->orderBy('id')->get();
        abort_if($models->isEmpty(), 404);

        $model = $models->first();               // canonical (lowest id) network
        $user = $request->user();

        $balance = $wallets->balanceFor($user, $model);
        $available = $balance->available;
        $locked = $balance->locked;
        $balanceTotal = $balance->total();

        // Networks a coin settles on (names only — the balance is pooled).
        $networks = $models->map(fn ($m) => [
            'chain' => $m->chain?->name ?? ($m->isFiat() ? 'Fiat' : '—'),
        ])->all();

        // Only offer Deposit/Withdraw when a rail actually exists: crypto settles
        // on-chain, while fiat needs operator-configured methods for this currency.
        $isFiat = $model->isFiat();
        $assetIds = $models->pluck('id')->all();
        $canDeposit = $isFiat ? $model->depositMethods()->exists() : true;
        $canWithdraw = $isFiat
            ? WithdrawalMethod::whereIn('asset_id', $assetIds)->where('is_active', true)->exists()
            : true;

        // Fiat value in the user's base currency (coin total × rate).
        $base = BaseCurrency::assetFor($user);
        $fiat = null;
        if ($base && $base->symbol !== $model->symbol) {
            $rate = app(RateProvider::class)->rate($model, $base);
            $whole = BigDecimal::ofUnscaledValue($balanceTotal->baseString(), $model->decimals)->multipliedBy($rate);
            $units = $whole->withPointMovedRight($base->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger();
            $fiat = Money::ofBase($units, $base->decimals, $base->symbol)->format(2);
        }

        return view('frontend.asset-show', [
            'canDeposit' => $canDeposit,
            'canWithdraw' => $canWithdraw,
            'asset' => [
                'id' => $model->id,
                'symbol' => $model->symbol,
                'name' => $model->name,
                'is_stablecoin' => (bool) $model->is_stablecoin,
                'is_fiat' => $model->isFiat(),
                // A single-network coin keeps its chain badge; a multi-network coin
                // shows its networks in the breakdown instead.
                'chain' => (count($networks) === 1 && $model->chain) ? [
                    'name' => $model->chain->name,
                    'color' => $model->chain->key->color(),
                ] : null,
            ],
            'networks' => count($networks) > 1 ? $networks : [],
            'balance' => [
                'available' => $available->format(),
                'locked' => $locked->format(),
                'total' => $balanceTotal->format(),
            ],
            'fiat' => $fiat,
            'transactions' => $this->transactions($user->id, $assetIds)->all(),
        ]);
    }

    /**
     * Per-asset activity feed. Each item carries a `rows` detail list (label/value,
     * with optional mono/copy/explorer) so the view can open a full record modal —
     * the same clickable-row → detail-modal pattern as the deposit history page.
     *
     * @param  array<int, int>  $assetIds
     */
    private function transactions(string $userId, array $assetIds): Collection
    {
        $deposits = Deposit::with(['asset.chain', 'depositMethod', 'onchainTx'])
            ->whereIn('asset_id', $assetIds)->where('user_id', $userId)->latest()->limit(20)->get()
            ->map(function (Deposit $d) {
                $tx = $d->onchainTx;
                $network = $d->asset->chain?->name ?? ($d->asset->isFiat() ? 'Fiat' : $d->asset->name);

                return $this->item($d->id, 'deposit', 'arrow-down-left', 'Deposit', $network,
                    '+'.$d->money()->format(), true, $d->status->label(), $d->status->color(), $d->created_at, [
                        $this->row(__('Gross amount'), $d->money()->format(), mono: true),
                        $d->fee > 0 ? $this->row(__('Platform fee'), '−'.$d->feeMoney()->format(), mono: true) : null,
                        $this->row(__('Net credited'), $d->netMoney()->format(), mono: true),
                        $this->row(__('Source'), $d->source === 'manual' ? ($d->depositMethod?->name ?? 'Manual') : 'On-chain'),
                        $this->row(__('Network'), $network),
                        $d->reference ? $this->row(__('Reference'), $d->reference) : null,
                        $tx?->confirmations !== null ? $this->row(__('Confirmations'), $tx->confirmations.' / '.$d->required_confirmations, mono: true) : null,
                        $this->row(__('Submitted'), $d->created_at->format('M j, Y · g:i A')),
                        $d->credited_at ? $this->row(__('Credited'), $d->credited_at->format('M j, Y · g:i A')) : null,
                        $this->hashRow(__('Transaction'), $tx?->tx_hash, $d->asset->chain?->explorerTxUrl($tx?->tx_hash)),
                        $this->hashRow(__('From address'), $tx?->from_address, $d->asset->chain?->explorerAddressUrl($tx?->from_address)),
                    ]);
            });

        $withdrawals = Withdrawal::with(['asset.chain', 'onchainTx'])
            ->whereIn('asset_id', $assetIds)->where('user_id', $userId)->latest()->limit(20)->get()
            ->map(function (Withdrawal $w) {
                $tx = $w->onchainTx;
                $network = $w->asset->chain?->name ?? ($w->asset->isFiat() ? 'Fiat' : $w->asset->name);
                $net = $w->fee > 0 ? $w->money()->minus($w->feeMoney()) : $w->money();

                return $this->item($w->id, 'withdrawal', 'arrow-up-right', 'Withdrawal',
                    $w->to_address ? 'To '.$this->shorten($w->to_address) : $network,
                    '-'.$w->money()->format(), false, $w->status->label(), $w->status->color(), $w->created_at, [
                        $this->row(__('Amount'), $w->money()->format(), mono: true),
                        $w->fee > 0 ? $this->row(__('Network fee'), '−'.$w->feeMoney()->format(), mono: true) : null,
                        $this->row(__('Net sent'), $net->format(), mono: true),
                        $this->row(__('Network'), $network),
                        $w->payout_method ? $this->row(__('Payout method'), (string) $w->payout_method) : null,
                        $this->row(__('Submitted'), $w->created_at->format('M j, Y · g:i A')),
                        $this->hashRow(__('Destination'), $w->to_address, $w->asset->chain?->explorerAddressUrl($w->to_address)),
                        $this->hashRow(__('Transaction'), $tx?->tx_hash, $w->asset->chain?->explorerTxUrl($tx?->tx_hash)),
                    ]);
            });

        $transfers = Transfer::with(['asset', 'sender', 'recipient'])
            ->whereIn('asset_id', $assetIds)
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->latest()->limit(20)->get()
            ->map(function (Transfer $t) use ($userId) {
                $sent = $t->sender_id === $userId;
                $counterparty = $sent
                    ? ($t->recipient?->name ?? $t->recipient_handle ?? '—')
                    : ($t->sender?->name ?? '—');

                return $this->item($t->id, 'transfer', $sent ? 'arrow-up-right' : 'arrow-down-left',
                    $sent ? 'Sent' : 'Received', ($sent ? 'To ' : 'From ').$counterparty,
                    ($sent ? '-' : '+').$t->money()->format(), ! $sent, $t->status->label(), $t->status->color(), $t->created_at, [
                        $this->row(__('Amount'), $t->money()->format(), mono: true),
                        $this->row($sent ? __('Recipient') : __('Sender'), $counterparty),
                        $t->memo ? $this->row(__('Note'), $t->memo) : null,
                        $this->row(__('Submitted'), $t->created_at->format('M j, Y · g:i A')),
                    ]);
            });

        // Swaps in/out of this coin (this coin may be the "from" or the "to" side).
        $swaps = Conversion::with('quote.fromAsset', 'quote.toAsset')
            ->where('user_id', $userId)
            ->whereHas('quote', fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('from_asset_id', $assetIds)->orWhereIn('to_asset_id', $assetIds)))
            ->latest()->limit(20)->get()
            ->filter(fn (Conversion $c) => $c->quote && $c->quote->fromAsset && $c->quote->toAsset)
            ->map(function (Conversion $c) use ($assetIds) {
                $from = $c->quote->fromAsset;
                $to = $c->quote->toAsset;
                $paid = Money::ofBase($c->quote->from_amount, $from->decimals, $from->symbol);
                $got = Money::ofBase($c->quote->to_amount, $to->decimals, $to->symbol);
                $spentThisCoin = in_array($from->id, $assetIds, true);

                return $this->item($c->id, 'swap', 'arrows-right-left', 'Swap '.$from->symbol.' → '.$to->symbol,
                    $from->symbol.' → '.$to->symbol,
                    ($spentThisCoin ? '-'.$paid->format() : '+'.$got->format()), ! $spentThisCoin,
                    ucfirst((string) $c->status), 'success', $c->created_at, [
                        $this->row(__('Paid'), $paid->format(), mono: true),
                        $this->row(__('Received'), $got->format(), mono: true),
                        $this->row(__('Rate'), '1 '.$from->symbol.' ≈ '.rtrim(rtrim($c->quote->rate, '0'), '.').' '.$to->symbol, mono: true),
                        $c->quote->spread_bps ? $this->row(__('Spread'), number_format($c->quote->spread_bps / 100, 2).'%') : null,
                        $c->quote->fee_bps ? $this->row(__('Fee'), number_format($c->quote->fee_bps / 100, 2).'%') : null,
                        $this->row(__('Submitted'), $c->created_at->format('M j, Y · g:i A')),
                        $c->completed_at ? $this->row(__('Completed'), $c->completed_at->format('M j, Y · g:i A')) : null,
                    ]);
            });

        return $deposits->concat($withdrawals)->concat($transfers)->concat($swaps)
            ->sortByDesc('at')->values();
    }

    /**
     * Shape one activity item. `$rows` may contain nulls (skipped fields); they are
     * filtered out so the modal renders only present detail rows.
     *
     * @param  array<int, array<string, mixed>|null>  $rows
     * @return array<string, mixed>
     */
    private function item(string $id, string $group, string $icon, string $title, ?string $subtitle,
        string $amount, bool $isCredit, string $status, string $statusColor, \DateTimeInterface $at, array $rows): array
    {
        return [
            'id' => $group.'-'.$id,
            'group' => $group,
            'icon' => $icon,
            'title' => $title,
            'subtitle' => $subtitle,
            'amount' => $amount,
            'isCredit' => $isCredit,
            'status' => $status,
            'statusColor' => $statusColor,
            'at' => \Illuminate\Support\Carbon::instance($at)->toIso8601String(),
            'rows' => array_values(array_filter($rows)),
        ];
    }

    /** @return array<string, mixed> */
    private function row(string $label, ?string $value, bool $mono = false): array
    {
        return ['label' => $label, 'value' => $value, 'mono' => $mono];
    }

    /** A row for a hash/address: shortened display, full-value copy, optional explorer link. Null if empty. */
    private function hashRow(string $label, ?string $value, ?string $explorer): ?array
    {
        if (! $value) {
            return null;
        }

        return ['label' => $label, 'value' => $this->shorten($value), 'copy' => $value, 'explorer' => $explorer, 'mono' => true];
    }

    private function shorten(string $value): string
    {
        return Str::length($value) > 20 ? Str::substr($value, 0, 10).'…'.Str::substr($value, -8) : $value;
    }
}
