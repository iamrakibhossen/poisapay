<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Transfer\ExecuteTransferAction;
use App\Domain\Wallet\WalletService;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Transfer;
use App\Models\User;
use App\Support\BaseCurrency;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Send money (peer transfer) — traditional server-rendered MVC. {@see index()}
 * folds the funded-wallet list + recent transfers into the view; {@see send()}
 * validates the recipient on submit and executes the transfer via
 * {@see ExecuteTransferAction}, redirecting back with a flash. Money-critical.
 */
class SendController extends Controller
{
    public function index(Request $request, WalletService $wallets): View
    {
        $user = $request->user();

        $walletRows = $wallets->fundedWallets($user)->map(fn ($b) => [
            'assetId' => $b->asset->id,
            'symbol' => $b->asset->symbol,
            'name' => $b->asset->name,
            'available' => $b->available->toDecimal(),
            'availableFormatted' => $b->available->format(),
        ])->values();

        return view('frontend.send', [
            'wallets' => $walletRows,
            'recentCount' => Transfer::where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('recipient_id', $user->id))->count(),
        ]);
    }

    /** Dedicated transfer history page — the full, paginated list of the user's transfers. */
    public function history(Request $request): View
    {
        $user = $request->user();
        $userId = $user->id;

        $filters = [
            'direction' => (string) $request->query('direction', 'all'),
            'asset' => (string) $request->query('asset', 'all'),
            'search' => trim((string) $request->query('search', '')),
        ];

        $mine = fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId);

        // ── Account-wide stats (independent of the active filters) ──
        $base = Transfer::where($mine);
        $stats = [
            'total' => (clone $base)->count(),
            'month' => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
            'sent' => $this->flowInBaseCurrency($user, 'sent'),
            'received' => $this->flowInBaseCurrency($user, 'received'),
        ];

        // Assets this user has ever transferred — powers the asset filter.
        $symbols = Asset::whereIn('id', (clone $base)->select('asset_id'))
            ->orderBy('symbol')->pluck('symbol');

        // ── Filtered, paginated feed ──
        $query = Transfer::with(['asset', 'sender', 'recipient'])->where($mine);

        if ($filters['direction'] === 'sent') {
            $query->where('sender_id', $userId);
        } elseif ($filters['direction'] === 'received') {
            $query->where('recipient_id', $userId);
        }

        if ($filters['asset'] !== 'all') {
            $query->whereHas('asset', fn ($q) => $q->where('symbol', $filters['asset']));
        }

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q
                ->where('memo', 'ilike', $term)
                ->orWhereHas('sender', fn ($s) => $s->where('name', 'ilike', $term))
                ->orWhereHas('recipient', fn ($r) => $r->where('name', 'ilike', $term)));
        }

        $transfers = $query
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Transfer $t) use ($userId) {
                $sent = $t->sender_id === $userId;
                $party = $sent ? $t->recipient : $t->sender;

                return [
                    'id' => $t->id,
                    'sent' => $sent,
                    'symbol' => $t->asset->symbol,
                    'name' => $t->asset->name,
                    'counterparty' => $party?->name ?? ($sent ? 'Recipient' : 'Sender'),
                    'counterpartyHandle' => $party?->email ?? $t->recipient_handle,
                    'memo' => $t->memo,
                    'amount' => $t->money()->format(),
                    'kind' => $t->kind?->label(),
                    'status' => $t->status?->label(),
                    'statusColor' => $t->status?->color(),
                    'at' => $t->created_at->toIso8601String(),
                ];
            });

        return view('frontend.transfers', [
            'transfers' => $transfers,
            'stats' => $stats,
            'filters' => $filters,
            'symbols' => $symbols,
        ]);
    }

    /**
     * All-time completed transfers in one direction, valued in the user's base
     * currency. Mirrors {@see \App\Domain\Analytics\FlowAnalytics} so figures never drift.
     */
    private function flowInBaseCurrency(User $user, string $direction): string
    {
        $base = BaseCurrency::assetFor($user);
        if (! $base) {
            return '—';
        }

        $rates = app(RateProvider::class);
        $total = BigDecimal::zero();

        $column = $direction === 'sent' ? 'sender_id' : 'recipient_id';
        foreach (Transfer::with('asset')->where($column, $user->id)->where('status', 'completed')->get() as $t) {
            $total = $total->plus(
                BigDecimal::ofUnscaledValue($t->amount, $t->asset->decimals)->multipliedBy($rates->rate($t->asset, $base))
            );
        }

        return Money::ofBase(
            $total->withPointMovedRight($base->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger(),
            $base->decimals,
            $base->symbol,
        )->format(2);
    }

    public function send(Request $request, ExecuteTransferAction $transfers): RedirectResponse
    {
        $validated = $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            'assetId' => ['required', 'integer'],
            'amount' => ['required', 'string'],
            'memo' => ['nullable', 'string', 'max:140'],
        ]);

        $asset = Asset::where('is_active', true)->find($validated['assetId']);
        if (! $asset) {
            throw ValidationException::withMessages(['assetId' => 'Please choose a valid asset.']);
        }

        $recipient = $this->resolveRecipient($validated['recipient']);
        if (! $recipient) {
            throw ValidationException::withMessages(['recipient' => 'No PaishaPay user found with that ID, email or phone.']);
        }

        if ($recipient->is($request->user())) {
            throw ValidationException::withMessages(['recipient' => 'You cannot send money to yourself.']);
        }

        try {
            $money = Money::ofDecimal($validated['amount'], $asset->decimals, $asset->symbol);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid amount.']);
        }

        if (! $money->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        try {
            $transfers->execute(
                sender: $request->user(),
                recipient: $recipient,
                asset: $asset,
                amount: $money,
                idempotencyKey: Str::uuid()->toString(),
                memo: ($validated['memo'] ?? '') !== '' ? $validated['memo'] : null,
            );
        } catch (\Throwable $e) {
            // Domain guard failures (insufficient funds, limits) surface as a form error.
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return redirect()->route('send.index')
            ->with('success', 'Sent '.$money->format().' to '.$recipient->name.'.');
    }

    private function resolveRecipient(string $query): ?User
    {
        $q = trim($query);

        return User::where('email', $q)
            ->orWhere('phone', $q)
            ->when(ctype_digit($q), fn ($b) => $b->orWhere('uid', $q))
            ->first();
    }
}
