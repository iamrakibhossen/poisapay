<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Transfer\ExecuteTransferAction;
use App\Domain\Wallet\WalletService;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Transfer;
use App\Models\User;
use App\Support\Money;
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
        $userId = $request->user()->id;

        $transfers = Transfer::with(['asset', 'sender', 'recipient'])
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->latest()
            ->paginate(20)
            ->through(function (Transfer $t) use ($userId) {
                $sent = $t->sender_id === $userId;

                return [
                    'sent' => $sent,
                    'symbol' => $t->asset->symbol,
                    'counterparty' => $sent ? ($t->recipient?->name ?? 'Recipient') : ($t->sender?->name ?? 'Sender'),
                    'memo' => $t->memo,
                    'amount' => $t->money()->format(),
                    'at' => $t->created_at->toIso8601String(),
                ];
            });

        return view('frontend.transfers', ['transfers' => $transfers]);
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
            throw ValidationException::withMessages(['recipient' => 'No PoisaPay user found with that ID, email or phone.']);
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
