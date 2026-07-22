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
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $base = Asset::where('currency_code', $user->base_currency)->first()
            ?? Asset::where('symbol', 'BDT')->first();
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

    /** @param  array<int, int>  $assetIds */
    private function transactions(string $userId, array $assetIds): Collection
    {
        $deposits = Deposit::whereIn('asset_id', $assetIds)->where('user_id', $userId)->latest()->limit(20)->get()
            ->map(fn ($d) => [
                'icon' => 'arrow-down-left', 'color' => 'success', 'title' => 'Deposit',
                'amount' => '+'.$d->money()->format(), 'status' => $d->status->label(),
                'at' => $d->created_at->toIso8601String(), 'at_human' => $d->created_at->diffForHumans(),
            ]);

        $withdrawals = Withdrawal::whereIn('asset_id', $assetIds)->where('user_id', $userId)->latest()->limit(20)->get()
            ->map(fn ($w) => [
                'icon' => 'arrow-up-right', 'color' => 'warning', 'title' => 'Withdrawal',
                'amount' => '-'.$w->money()->format(), 'status' => $w->status->label(),
                'at' => $w->created_at->toIso8601String(), 'at_human' => $w->created_at->diffForHumans(),
            ]);

        $transfers = Transfer::whereIn('asset_id', $assetIds)
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->latest()->limit(20)->get()
            ->map(fn ($t) => [
                'icon' => 'paper-airplane', 'color' => 'info',
                'title' => $t->sender_id === $userId ? 'Sent' : 'Received',
                'amount' => ($t->sender_id === $userId ? '-' : '+').$t->money()->format(),
                'status' => $t->status->label(),
                'at' => $t->created_at->toIso8601String(), 'at_human' => $t->created_at->diffForHumans(),
            ]);

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
                $spentThisCoin = in_array($from->id, $assetIds, true);
                $amount = $spentThisCoin
                    ? '-'.Money::ofBase($c->quote->from_amount, $from->decimals, $from->symbol)->format()
                    : '+'.Money::ofBase($c->quote->to_amount, $to->decimals, $to->symbol)->format();

                return [
                    'icon' => 'arrows-right-left', 'color' => 'info', 'title' => 'Swap '.$from->symbol.' → '.$to->symbol,
                    'amount' => $amount, 'status' => 'Completed',
                    'at' => $c->created_at->toIso8601String(), 'at_human' => $c->created_at->diffForHumans(),
                ];
            });

        return $deposits->concat($withdrawals)->concat($transfers)->concat($swaps)
            ->sortByDesc('at')->values();
    }
}
