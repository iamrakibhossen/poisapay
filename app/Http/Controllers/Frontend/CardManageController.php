<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Card\CardService;
use App\Card\Enums\ProviderCapability;
use App\Card\Exceptions\FeatureNotSupportedException;
use App\Domain\Card\CardStatementService;
use App\Domain\Card\CloseCardAction;
use App\Domain\Card\OpenCardDisputeAction;
use App\Domain\Card\ReplaceCardAction;
use App\Domain\Card\SetCardPinAction;
use App\Domain\Card\UpdateCardControlsAction;
use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardAuthorization;
use App\Models\CardDispute;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Card management (detail) page — server-rendered. Ownership is enforced on every
 * request — a non-owner gets a 403. All data (card, statement, analytics,
 * authorizations) is rendered directly in the Blade view; mutations are form POSTs
 * that redirect back with a flash message (or validation errors).
 *
 * SECURITY: the page itself only ever renders masked values (last4 + masked
 * expiry/CVV). Full details are revealed out-of-band via {@see self::revealSession()}:
 * for real providers the PAN/CVV render inside the issuer's PCI-DSS iframe in the
 * user's browser and never touch PoisaPay servers (we only mint a scoped client key).
 */
class CardManageController extends Controller
{
    public function index(Request $request, string $card, CardStatementService $statements, CardService $cards): View
    {
        $model = Card::with('provider')->where('user_id', $request->user()->id)->find($card);
        abort_unless($model !== null && $model->user_id === $request->user()->id, 403);

        // Reveal is driven client-side; expose only what the browser needs. The
        // publishable key is safe to expose; issuer_card_ref is Stripe.js's own handle.
        $provider = $cards->forCard($model);
        $revealDriver = $provider->key();
        $canReveal = feature('card_reveal_enabled', true)
            && $provider->supports(ProviderCapability::RevealPan)
            && $model->status !== CardStatus::Closed;
        $stripePublishableKey = $revealDriver === 'stripe'
            ? (string) config('card.providers.stripe.publishable_key')
            : null;
        // Stripe.js needs the issuing card id (ic_…) client-side to run its PCI handshake;
        // it's not a secret. Every other driver resolves the card server-side by our id,
        // so the opaque issuer token never leaves the server.
        $issuerCardRef = $revealDriver === 'stripe' ? $model->issuer_card_ref : null;

        $monthParam = (string) $request->query('month', '');
        $month = $monthParam !== ''
            ? (CarbonImmutable::createFromFormat('Y-m', $monthParam) ?: CarbonImmutable::now())
            : CarbonImmutable::now();

        $from = $month->startOfMonth();
        $to = $month->isSameMonth(CarbonImmutable::now()) ? CarbonImmutable::now() : $month->endOfMonth();

        $statement = $statements->forPeriod($model, $from, $to);
        $analytics = $statements->analytics($model, 6);
        $analyticsMax = $analytics->max('spent_minor') ?: 1;

        $auths = $model->authorizations()->latest()->limit(20)->get();

        $disputedIds = CardDispute::whereIn('authorization_id', $auths->pluck('id'))
            ->whereIn('status', ['open', 'represented'])
            ->pluck('authorization_id')
            ->all();

        $monthOptions = collect(range(0, 5))
            ->map(function (int $i) {
                $m = CarbonImmutable::now()->subMonths($i);

                return ['value' => $m->format('Y-m'), 'label' => $m->format('F Y')];
            })->values();

        return view('frontend.card-manage', [
            'card' => $model,
            'holderName' => $request->user()->name ?: 'Card Holder',
            'currency' => $model->settlement_currency,
            'canReveal' => $canReveal,
            'revealDriver' => $revealDriver,
            'issuerCardRef' => $issuerCardRef,
            'stripePublishableKey' => $stripePublishableKey,
            'selectedMonth' => $month->format('Y-m'),
            'monthOptions' => $monthOptions,
            'statement' => [
                'from' => $statement['from'],
                'to' => $statement['to'],
                'settled' => number_format($statement['settled_minor'] / 100, 2),
                'refunded' => number_format($statement['refunded_minor'] / 100, 2),
                'count' => $statement['count'],
                'byMcc' => collect($statement['by_mcc'])
                    ->map(fn (int $amount, string $mcc) => [
                        'mcc' => $mcc,
                        'amount' => number_format($amount / 100, 2),
                    ])->values()->all(),
            ],
            'analytics' => $analytics->map(function (array $row, string $ym) use ($analyticsMax) {
                return [
                    'label' => CarbonImmutable::createFromFormat('Y-m', $ym)->format('M Y'),
                    'amount' => number_format($row['spent_minor'] / 100, 2),
                    'count' => $row['count'],
                    'pct' => max(4, (int) round($row['spent_minor'] / $analyticsMax * 100)),
                ];
            })->values()->all(),
            'auths' => $auths->map(fn (CardAuthorization $a) => [
                'id' => $a->id,
                'merchant' => $a->merchant ?? '—',
                'mcc' => $a->mcc ?? '—',
                'amount' => $a->currency_code.' '.number_format((int) $a->amount / 100, 2),
                'status' => $a->status->value,
                'statusLabel' => $a->status->label(),
                'statusColor' => $a->status->color(),
                'date' => $a->created_at?->format('M j, Y'),
                'disputable' => $a->status->value === 'settled' && ! in_array($a->id, $disputedIds, true),
                'disputed' => in_array($a->id, $disputedIds, true),
            ])->all(),
        ]);
    }

    /**
     * Mint a short-lived reveal session so the user's browser can display full card
     * details straight from the issuer. Step-up guarded (re-enter password) and
     * rate-limited at the route. Returns JSON — the one place the frontend goes async,
     * because Stripe's PCI Elements are an inherently client-side + nonce handshake.
     * For real providers the JSON carries only a scoped client key, never a PAN.
     */
    public function revealSession(Request $request, string $card, CardService $cards): JsonResponse
    {
        abort_unless(feature('card_reveal_enabled', true), 404);

        $model = $this->resolve($request, $card);

        $validated = $request->validate([
            'password' => 'required|string',
            'nonce' => 'nullable|string|max:255', // Stripe client nonce; absent for the mock driver
        ]);

        // Step-up: re-verify the account password before exposing card details.
        if (! Hash::check($validated['password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        if ($model->status === CardStatus::Closed) {
            throw ValidationException::withMessages(['card' => __('A closed card has no details to reveal.')]);
        }

        try {
            $session = $cards->revealSession($model, ['nonce' => (string) ($validated['nonce'] ?? '')]);
        } catch (FeatureNotSupportedException) {
            return response()->json(['message' => __('Card detail reveal is not available for this card.')], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Unable to load card details right now. Please try again.')], 502);
        }

        return response()->json($session->toArray());
    }

    public function saveControls(Request $request, string $card, UpdateCardControlsAction $action): RedirectResponse
    {
        $model = $this->resolve($request, $card);

        $validated = $request->validate([
            'nickname' => 'nullable|string|max:40',
            'online_enabled' => 'boolean',
            'atm_enabled' => 'boolean',
            'contactless_enabled' => 'boolean',
            'daily_limit' => 'nullable|numeric|min:0',
            'per_tx_limit' => 'nullable|numeric|min:0',
            'allowed_countries' => 'nullable|string|max:255',
            'blocked_mccs' => 'nullable|string|max:255',
        ]);

        try {
            $action->execute($model, [
                'nickname' => ($validated['nickname'] ?? '') !== '' ? $validated['nickname'] : null,
                'online_enabled' => (bool) ($validated['online_enabled'] ?? false),
                'atm_enabled' => (bool) ($validated['atm_enabled'] ?? false),
                'contactless_enabled' => (bool) ($validated['contactless_enabled'] ?? false),
                'daily_limit' => ($validated['daily_limit'] ?? '') !== '' ? (int) round(((float) $validated['daily_limit']) * 100) : null,
                'per_tx_limit' => ($validated['per_tx_limit'] ?? '') !== '' ? (int) round(((float) $validated['per_tx_limit']) * 100) : null,
                'allowed_countries' => $validated['allowed_countries'] ?? '',
                'blocked_mccs' => $validated['blocked_mccs'] ?? '',
            ]);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['nickname' => $e->getMessage()]);
        }

        return redirect()->route('cards.manage', $model->id)->with('success', 'Card controls updated.');
    }

    public function setPin(Request $request, string $card, SetCardPinAction $action): RedirectResponse
    {
        $model = $this->resolve($request, $card);

        $validated = $request->validate([
            'pin' => 'required|digits_between:4,6',
        ]);

        try {
            $action->execute($model, $validated['pin']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['pin' => $e->getMessage()]);
        }

        return redirect()->route('cards.manage', $model->id)->with('success', 'Card PIN set.');
    }

    public function toggleFreeze(Request $request, string $card, CardService $cards): RedirectResponse
    {
        $model = $this->resolve($request, $card);

        if ($model->status === CardStatus::Closed) {
            throw ValidationException::withMessages(['card' => 'A closed card cannot be frozen.']);
        }

        $frozen = $model->status === CardStatus::Frozen ? $cards->unfreeze($model) : $cards->freeze($model);

        return redirect()->route('cards.manage', $model->id)
            ->with('success', $frozen->status === CardStatus::Frozen ? 'Card frozen.' : 'Card unfrozen.');
    }

    public function replace(Request $request, string $card, ReplaceCardAction $action): RedirectResponse
    {
        $model = $this->resolve($request, $card);

        $validated = $request->validate([
            'reason' => 'required|in:lost,stolen,damaged',
        ]);

        try {
            $new = $action->execute($model, $validated['reason']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return redirect()->route('cards.manage', $new->id)->with('success', 'Replacement card issued.');
    }

    public function close(Request $request, string $card, CloseCardAction $action): RedirectResponse
    {
        $model = $this->resolve($request, $card);

        try {
            $action->execute($model, 'user_requested');
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['card' => $e->getMessage()]);
        }

        return redirect()->route('cards')->with('success', 'Card closed.');
    }

    public function submitDispute(Request $request, string $card, OpenCardDisputeAction $action): RedirectResponse
    {
        $model = $this->resolve($request, $card);

        $validated = $request->validate([
            'authId' => 'required|string',
            'reason' => 'required|in:fraud,not_received,duplicate,incorrect_amount',
        ]);

        $auth = $model->authorizations()->find($validated['authId']);
        if (! $auth) {
            throw ValidationException::withMessages(['authId' => 'Purchase not found.']);
        }

        try {
            $action->execute($auth, $validated['reason']);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return redirect()->route('cards.manage', $model->id)->with('success', 'Dispute opened.');
    }

    private function resolve(Request $request, string $card): Card
    {
        $model = Card::where('user_id', $request->user()->id)->find($card);
        abort_unless($model !== null && $model->user_id === $request->user()->id, 403);

        return $model;
    }
}
