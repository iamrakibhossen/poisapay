<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Auth\TwoFactorService;
use App\Domain\Fees\PlatformFees;
use App\Domain\Wallet\WalletService;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\PayoutAccount;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Withdraw — traditional server-rendered MVC. Currency-first → network →
 * amount/address/2FA flow, with the step held in the query string (?coin, ?asset).
 * A coin like USDT lives on several chains, shown as explicit networks.
 * {@see index()} groups funded crypto wallets into coins, renders the chosen
 * network's fee/available/saved-addresses, and the final form. {@see submit()}
 * reserves funds via {@see RequestWithdrawalAction} behind a two-factor gate.
 * Money-critical.
 */
class WithdrawController extends Controller
{
    public function index(Request $request, WalletService $wallets): View
    {
        $user = $request->user();
        $funded = $this->withdrawableWallets($wallets, $user);

        // Fiat balances can be cashed out to a bank account or mobile wallet —
        // but only for currencies with an active payout method (same rule as deposits).
        $payoutFiatIds = WithdrawalMethod::where('is_active', true)->distinct()->pluck('asset_id');
        $cashOptions = $wallets->fundedWallets($user)
            ->filter(fn ($w) => $w->asset->isFiat() && $payoutFiatIds->contains($w->asset->id))
            ->map(fn ($w) => [
                'assetId' => $w->asset->id,
                'symbol' => $w->asset->symbol,
                'name' => $w->asset->name,
                'available' => $w->available->format(),
            ])->values();

        // Fiat cash-out step (?cash={fiatAssetId}).
        $fiatDetail = null;
        if ($cashAssetId = $request->query('cash')) {
            $fiatDetail = $this->fiatDetail($request, $wallets, (int) $cashAssetId);
        }

        $coins = $funded
            ->groupBy(fn ($w) => $w->asset->symbol)
            ->map(function ($group) {
                $networks = $group->map(fn ($w) => [
                    'assetId' => $w->asset->id,
                    'chainName' => $w->asset->chain?->name ?? $w->asset->name,
                    'available' => $w->available->format(),
                ])->values();

                $total = $group->reduce(fn (?Money $carry, $w) => $carry ? $carry->plus($w->available) : $w->available, null);

                return [
                    'symbol' => $group->first()->asset->symbol,
                    'name' => $group->first()->asset->name,
                    'total' => $total?->format(),
                    'networkCount' => $networks->count(),
                    'networks' => $networks,
                ];
            })
            ->sortBy('symbol')->values();

        // Resolve the current step from the query string.
        $coin = $request->query('coin') ? (string) $request->query('coin') : null;
        $selectedCoin = $coin ? $coins->firstWhere('symbol', $coin) : null;
        $coin = $selectedCoin['symbol'] ?? null;

        $networkDetail = null;
        $assetId = $request->query('asset');
        if ($coin && $assetId) {
            $networkDetail = $this->networkDetail($request, $wallets, (int) $assetId, $coin);
            if (! $networkDetail) {
                $assetId = null;
            }
        } else {
            $assetId = null;
        }

        return view('frontend.withdraw', [
            'enabled' => (bool) feature('withdrawal_enabled', true),
            'requires2fa' => $user->hasTwoFactorEnabled(),
            'coins' => $coins,
            'coin' => $coin,
            'selectedCoin' => $selectedCoin,
            'assetId' => $assetId,
            'networkDetail' => $networkDetail,
            'cashOptions' => $cashOptions,
            'fiatDetail' => $fiatDetail,
            'recentCount' => Withdrawal::where('user_id', $user->id)->count(),
        ]);
    }

    /** Dedicated withdrawal history page — the full, paginated list of the user's withdrawals. */
    public function history(Request $request): View
    {
        $withdrawals = Withdrawal::with('asset.chain', 'onchainTx')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20)
            ->through(function (Withdrawal $w) {
                $hash = $w->onchainTx?->tx_hash;

                return [
                    'symbol' => $w->asset->symbol,
                    'name' => $w->asset->name,
                    'network' => $w->asset->chain?->name ?? ($w->asset->isFiat() ? 'Cash-out' : $w->asset->name),
                    'amount' => $w->money()->format(),
                    'fee' => $w->fee > 0 ? $w->asset->money($w->fee)->format() : null,
                    'to' => $w->to_address ? $this->shorten($w->to_address) : null,
                    'status' => $w->status->label(),
                    'statusColor' => $w->status->color(),
                    'at' => $w->created_at->toIso8601String(),
                    'txid' => $hash,
                    'txidShort' => $hash ? Str::substr($hash, 0, 10).'…'.Str::substr($hash, -8) : null,
                    'explorer' => $w->asset->chain?->explorerTxUrl($hash),
                ];
            });

        return view('frontend.withdrawals', ['withdrawals' => $withdrawals]);
    }

    /**
     * Fiat cash-out detail for a funded fiat asset: the operator-configured payout
     * methods for this currency + the user's saved payout accounts.
     *
     * @return array<string, mixed>|null
     */
    private function fiatDetail(Request $request, WalletService $wallets, int $assetId): ?array
    {
        $asset = Asset::where('is_active', true)->find($assetId);
        if (! $asset || ! $asset->isFiat()) {
            return null;
        }

        $available = $wallets->balanceFor($request->user(), $asset)->available;
        $fee = $asset->money($asset->withdrawal_fee);

        // Methods are dynamic per currency (operator-configured).
        $methods = WithdrawalMethod::where('asset_id', $asset->id)->where('is_active', true)
            ->orderBy('sort')->orderBy('name')->get()
            ->map(fn (WithdrawalMethod $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->type,
                'isBank' => $m->isBank(),
                'numberLabel' => $m->details['number_label'] ?? 'Account number',
                'min' => $m->minMoney()->format(),
                'max' => $m->maxMoney()?->format(),
                'minNum' => (float) $m->minMoney()->toDecimal(),
                'maxNum' => $m->maxMoney() ? (float) $m->maxMoney()->toDecimal() : null,
                'feeFixed' => (float) $asset->money($m->fixed_fee ?? '0')->toDecimal(),
                'feeBps' => (int) $m->percent_fee_bps,
                'feeLabel' => $this->feeLabel($asset, $m),
            ])->values()->all();

        // The user's saved accounts for this currency.
        $accounts = PayoutAccount::with('method')
            ->where('user_id', $request->user()->id)->where('asset_id', $asset->id)
            ->orderByDesc('is_favorite')->orderByDesc('last_used_at')->get()
            ->map(fn (PayoutAccount $a) => [
                'id' => $a->id,
                'methodId' => $a->method?->id,
                'label' => $a->displayLabel(),
                'methodName' => $a->method?->name,
                'accountName' => $a->account_name,
                'accountNumber' => $a->account_number,
            ])->values()->all();

        return [
            'assetId' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'available' => $available->format(),
            'availableDecimal' => $available->toDecimal(),
            'decimals' => $asset->decimals,
            'fee' => $fee->format(),
            'feePercent' => (float) PlatformFees::withdrawalPercent(),
            'min' => $asset->money($asset->withdrawal_min)->format(),
            'max' => $this->maxWithdrawable($available, $fee, $asset),
            'methods' => $methods,
            'accounts' => $accounts,
        ];
    }

    /** Human fee summary for a payout rail, e.g. "10.00 BDT + 1%" or "No fee". */
    private function feeLabel(Asset $asset, WithdrawalMethod $method): string
    {
        $parts = [];
        $fixed = $asset->money($method->fixed_fee ?? '0');
        if (! $fixed->isZero()) {
            $parts[] = $fixed->format();
        }
        if ($method->percent_fee_bps > 0) {
            $parts[] = rtrim(rtrim(number_format($method->percent_fee_bps / 100, 2), '0'), '.').'%';
        }

        return $parts ? implode(' + ', $parts) : 'No fee';
    }

    /**
     * Fiat cash-out to a saved account or a new bank/mobile account (reserves funds
     * for an operator payout). Optionally saves the new account for reuse.
     */
    public function submitFiat(Request $request, RequestWithdrawalAction $action, TwoFactorService $twoFactor): RedirectResponse
    {
        $validated = $request->validate([
            'assetId' => ['required', 'integer'],
            'accountId' => ['nullable', 'string'],
            'methodId' => ['required_without:accountId', 'nullable', 'string'],
            'amount' => ['required', 'string'],
            'accountName' => ['required_without:accountId', 'nullable', 'string', 'max:120'],
            'accountNumber' => ['required_without:accountId', 'nullable', 'string', 'max:64'],
            'bankName' => ['nullable', 'string', 'max:120'],
            'saveAccount' => ['nullable', 'boolean'],
            'label' => ['nullable', 'string', 'max:60'],
            'twoFactorCode' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $asset = Asset::where('is_active', true)->find($validated['assetId']);
        if (! $asset || ! $asset->isFiat()) {
            throw ValidationException::withMessages(['assetId' => 'Please choose a valid currency to cash out.']);
        }

        // Resolve the destination — a saved account or a new one against a configured method.
        [$method, $account, $name, $number, $bankName] = $this->resolvePayoutTarget($request, $user, $asset, $validated);

        if ($user->hasTwoFactorEnabled()) {
            $code = trim((string) ($validated['twoFactorCode'] ?? ''));
            if ($code === '' || ! $twoFactor->verify($user, $code)) {
                throw ValidationException::withMessages(['twoFactorCode' => 'Enter a valid two-factor code to continue.']);
            }
        }

        try {
            $money = Money::ofDecimal($validated['amount'], $asset->decimals, $asset->symbol);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid amount.']);
        }
        if (! $money->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }
        if ($method) {
            if ($money->isLessThan($method->minMoney())) {
                throw ValidationException::withMessages(['amount' => "Minimum for {$method->name} is {$method->minMoney()->format()}."]);
            }
            if (($mMax = $method->maxMoney()) && $mMax->isLessThan($money)) {
                throw ValidationException::withMessages(['amount' => "Maximum for {$method->name} is {$mMax->format()}."]);
            }
        }

        $type = $method?->type ?? ($bankName ? 'bank' : 'mobile');
        $reference = ($method?->name ?? $bankName ?? 'Payout').' •••'.substr($number, -4);
        $details = array_filter([
            'method' => $method?->name,
            'bank_name' => $bankName,
            'account_name' => $name,
            'account_number' => $number,
        ], fn ($v) => $v !== null && $v !== '');

        // The payout rail's own fee (fixed + percent) applies, not the asset default.
        $fee = $method ? $method->feeFor($money) : null;

        try {
            $withdrawal = $action->execute(
                user: $user,
                asset: $asset,
                amount: $money,
                toAddress: $reference,
                idempotencyKey: Str::uuid()->toString(),
                payoutMethod: $type,
                payoutDetails: $details,
                feeOverride: $fee,
            );
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[in_array($field, ['amount'], true) ? 'amount' : 'accountNumber'] = $messages[0];
            }
            throw ValidationException::withMessages($errors);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        // Persist / touch the saved account.
        $this->rememberAccount($user, $asset, $method, $account, $name, $number, $bankName, $validated);

        return redirect()->route('withdraw.index')->with(
            'success',
            'Cash-out of '.$withdrawal->money()->format().' requested to '.$reference.' — we\'ll process it after review.'
        );
    }

    /** Remove a saved payout account. */
    public function deleteAccount(Request $request, string $id): RedirectResponse
    {
        $account = PayoutAccount::where('user_id', $request->user()->id)->findOrFail($id);
        $assetId = $account->asset_id;
        $account->delete();

        return redirect()->route('withdraw.index', ['cash' => $assetId])->with('success', 'Payout account removed.');
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array{0: ?WithdrawalMethod, 1: ?PayoutAccount, 2: string, 3: string, 4: ?string}
     */
    private function resolvePayoutTarget(Request $request, $user, Asset $asset, array $v): array
    {
        if (! empty($v['accountId'])) {
            $account = PayoutAccount::with('method')
                ->where('user_id', $user->id)->where('asset_id', $asset->id)->findOrFail($v['accountId']);

            return [$account->method, $account, $account->account_name, $account->account_number, $account->bank_name];
        }

        $method = WithdrawalMethod::where('asset_id', $asset->id)->where('is_active', true)->find($v['methodId']);
        if (! $method) {
            throw ValidationException::withMessages(['methodId' => 'Please choose a valid payout method.']);
        }

        $bankName = trim((string) ($v['bankName'] ?? ''));
        if ($method->isBank() && $bankName === '') {
            throw ValidationException::withMessages(['bankName' => 'Enter the bank name.']);
        }

        return [$method, null, trim($v['accountName']), trim($v['accountNumber']), $method->isBank() ? $bankName : null];
    }

    /**
     * @param  array<string, mixed>  $v
     */
    private function rememberAccount($user, Asset $asset, ?WithdrawalMethod $method, ?PayoutAccount $account, string $name, string $number, ?string $bankName, array $v): void
    {
        try {
            if ($account) {
                $account->update(['last_used_at' => now()]);

                return;
            }

            if (! ($v['saveAccount'] ?? false) || ! $method) {
                return;
            }

            PayoutAccount::updateOrCreate(
                ['user_id' => $user->id, 'withdrawal_method_id' => $method->id, 'account_number' => $number],
                [
                    'asset_id' => $asset->id,
                    'label' => ($v['label'] ?? '') !== '' ? $v['label'] : null,
                    'account_name' => $name,
                    'bank_name' => $bankName,
                    'last_used_at' => now(),
                ],
            );
        } catch (\Throwable) {
            // Non-fatal: the withdrawal already succeeded.
        }
    }

    /**
     * Per-network detail (available/fee/max/saved addresses) for the chosen chain.
     *
     * @return array<string, mixed>|null
     */
    private function networkDetail(Request $request, WalletService $wallets, int $assetId, string $coin): ?array
    {
        $asset = Asset::where('is_active', true)->find($assetId);
        if (! $asset || $asset->isFiat() || $asset->symbol !== $coin) {
            return null;
        }

        $available = $wallets->balanceFor($request->user(), $asset)->available;
        $fee = $asset->money($asset->withdrawal_fee);

        return [
            'assetId' => $asset->id,
            'symbol' => $asset->symbol,
            'network' => $asset->chain?->name ?? $asset->name,
            'available' => $available->format(),
            'availableDecimal' => $available->toDecimal(),
            'decimals' => $asset->decimals,
            'fee' => $fee->format(),
            'feeDecimal' => $fee->toDecimal(),
            'feePercent' => (float) PlatformFees::withdrawalPercent(),
            'max' => $this->maxWithdrawable($available, $fee, $asset),
        ];
    }

    /**
     * Largest amount whose amount + flat fee + platform % ≤ available, i.e.
     * (available − flatFee) / (1 + percent/100), floored to the asset's scale.
     */
    private function maxWithdrawable(Money $available, Money $flatFee, Asset $asset): string
    {
        $net = $available->minus($flatFee);
        if (! $net->isPositive()) {
            return '0';
        }

        $divisor = BigDecimal::of('1')->plus(BigDecimal::of(PlatformFees::withdrawalPercent())->dividedBy(100, 18, RoundingMode::DOWN));

        return (string) BigDecimal::of($net->toDecimal())->dividedBy($divisor, $asset->decimals, RoundingMode::DOWN);
    }

    public function submit(Request $request, RequestWithdrawalAction $action, TwoFactorService $twoFactor): RedirectResponse
    {
        $validated = $request->validate([
            'assetId' => ['required', 'integer'],
            'toAddress' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'string'],
            'memo' => ['nullable', 'string', 'max:140'],
            'twoFactorCode' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $asset = Asset::where('is_active', true)->find($validated['assetId']);
        if (! $asset) {
            throw ValidationException::withMessages(['assetId' => 'Please choose a valid asset.']);
        }

        // Two-factor gate: withdrawals are sensitive (§8.2).
        if ($user->hasTwoFactorEnabled()) {
            $code = trim((string) ($validated['twoFactorCode'] ?? ''));
            if ($code === '') {
                throw ValidationException::withMessages(['twoFactorCode' => 'Enter your two-factor code to continue.']);
            }
            if (! $twoFactor->verify($user, $code)) {
                throw ValidationException::withMessages(['twoFactorCode' => 'That two-factor code is invalid or expired.']);
            }
        }

        try {
            $money = Money::ofDecimal($validated['amount'], $asset->decimals, $asset->symbol);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid amount.']);
        }

        if (! $money->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $toAddress = trim($validated['toAddress']);

        try {
            $withdrawal = $action->execute(
                user: $user,
                asset: $asset,
                amount: $money,
                toAddress: $toAddress,
                idempotencyKey: Str::uuid()->toString(),
            );
        } catch (ValidationException $e) {
            // Re-map the domain field errors onto the form fields.
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $target = in_array($field, ['amount', 'toAddress'], true) ? $field : 'toAddress';
                $errors[$target] = $messages[0];
            }
            throw ValidationException::withMessages($errors);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        ActivityLogger::log('withdrawal.requested.ui', $withdrawal);

        $message = $withdrawal->requires_review
            ? 'Withdrawal of '.$withdrawal->money()->format().' submitted — queued for review.'
            : 'Withdrawal of '.$withdrawal->money()->format().' submitted.';

        return redirect()->route('withdraw.index')->with('success', $message);
    }

    /** Funded crypto wallets only — fiat is not withdrawable on-chain. */
    private function withdrawableWallets(WalletService $wallets, $user)
    {
        return $wallets->fundedWallets($user)
            ->filter(fn ($w) => ! $w->asset->isFiat())
            ->values();
    }

    private function shorten(string $address): string
    {
        return mb_strlen($address) > 14 ? mb_substr($address, 0, 6).'…'.mb_substr($address, -4) : $address;
    }
}
