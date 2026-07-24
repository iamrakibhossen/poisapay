<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Card\CardService;
use App\Domain\Card\CardPricing;
use App\Domain\Card\CardStatementService;
use App\Domain\Card\GenerateCardAction;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardStatus;
use App\Enums\CardType;
use App\Enums\KycTier;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Card;
use App\Models\CardProvider;
use App\Support\KycPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Cards list — server-rendered. The controller builds the card portfolio, spend
 * summary and provider options and hands them to the Blade view; mutations are
 * plain form POSTs that redirect back with a flash message (or validation errors).
 * Cards never store or return a PAN/CVV — only masked issuer data (last4).
 */
class CardsController extends Controller
{
    public function index(Request $request, CardStatementService $statements, LedgerService $ledger): View
    {
        $user = $request->user();

        $provider = $this->defaultProvider();
        $cards = Card::with('provider')->where('user_id', $user->id)->latest()->get();

        // Portfolio + per-card spend summary — settled this month across all cards.
        $from = CarbonImmutable::now()->startOfMonth();
        $to = CarbonImmutable::now();
        $monthSpentMinor = 0;
        $monthTxCount = 0;
        $monthCurrency = null;
        $spendByCard = [];
        foreach ($cards as $card) {
            $statement = $statements->forPeriod($card, $from, $to);
            $monthSpentMinor += $statement['settled_minor'];
            $monthTxCount += $statement['count'];
            $monthCurrency ??= $card->settlement_currency;
            $spendByCard[$card->id] = [
                'spent' => number_format($statement['settled_minor'] / 100, 2),
                'count' => $statement['count'],
                'currency' => $card->settlement_currency,
            ];
        }

        // Issuance pricing (admin-configurable) + the user's stablecoin balance,
        // so the create modal can show the price and afford-ability up front.
        $pricing = [
            'virtual' => ['label' => CardPricing::label(CardType::Virtual), 'free' => CardPricing::isFree(CardType::Virtual)],
            'physical' => ['label' => CardPricing::label(CardType::Physical), 'free' => CardPricing::isFree(CardType::Physical)],
        ];
        $fundingAsset = Asset::where('symbol', CardPricing::fundingAsset())->where('is_active', true)->first();
        $fundingAvailable = $fundingAsset
            ? $ledger->availableBalance($user, $fundingAsset->id)->format()
            : null;

        return view('frontend.cards', [
            'canIssue' => $user->tier() === KycTier::Full,
            'canCreate' => $provider !== null,
            'supportsPhysical' => (bool) $provider?->supports_physical,
            'settlementCurrency' => $provider?->settlement_currency ?? 'USD',
            'cardNetwork' => ucfirst((string) ($provider?->network ?? 'visa')),
            'holderName' => $user->name ?: 'Card Holder',
            'cards' => $cards,
            'spendByCard' => $spendByCard,
            'monthSpent' => number_format($monthSpentMinor / 100, 2),
            'monthTxCount' => $monthTxCount,
            'monthCurrency' => $monthCurrency ?? 'USD',
            'activeCount' => $cards->where('status', CardStatus::Active)->count(),
            'pricing' => $pricing,
            'fundingSymbol' => CardPricing::fundingAsset(),
            'fundingAvailable' => $fundingAvailable,
        ]);
    }

    public function generate(Request $request, GenerateCardAction $action): RedirectResponse
    {
        $user = $request->user();

        if (! KycPolicy::canIssueCard($user->tier())) {
            throw ValidationException::withMessages([
                'cardType' => 'Full verification is required to issue a card.',
            ]);
        }

        $validated = $request->validate([
            'cardType' => ['required', 'in:virtual,physical'],
        ]);

        // Provider is chosen for the user — the platform routes to the configured issuer.
        $provider = $this->defaultProvider();
        if (! $provider) {
            throw ValidationException::withMessages(['cardType' => 'Card issuance is temporarily unavailable.']);
        }

        try {
            $action->execute($user, $provider, CardType::from($validated['cardType']));
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['cardType' => $e->getMessage()]);
        }

        return redirect()->route('cards')->with('success', 'Your card is ready.');
    }

    /** The issuer the platform provisions through — the configured default driver, else the first active. */
    private function defaultProvider(): ?CardProvider
    {
        $driver = (string) config('card.default_provider');

        return CardProvider::where('is_active', true)->where('driver', $driver)->orderBy('sort')->first()
            ?? CardProvider::where('is_active', true)->orderBy('sort')->first();
    }

    public function activate(Request $request, string $card): RedirectResponse
    {
        $model = $this->resolve($request, $card);

        if ($model->status !== CardStatus::Inactive) {
            throw ValidationException::withMessages(['card' => 'This card cannot be activated.']);
        }

        $model->status = CardStatus::Active;
        $model->save();

        return redirect()->route('cards')->with('success', 'Card activated.');
    }

    public function toggleFreeze(Request $request, string $card, CardService $cards): RedirectResponse
    {
        $model = $this->resolve($request, $card);

        if ($model->status === CardStatus::Closed) {
            throw ValidationException::withMessages(['card' => 'A closed card cannot be frozen.']);
        }

        if ($model->status === CardStatus::Frozen) {
            $cards->unfreeze($model);

            return redirect()->route('cards')->with('success', 'Card unfrozen.');
        }

        $cards->freeze($model);

        return redirect()->route('cards')->with('success', 'Card frozen.');
    }

    private function resolve(Request $request, string $card): Card
    {
        $model = Card::where('user_id', $request->user()->id)->find($card);
        abort_unless($model !== null && $model->user_id === $request->user()->id, 403);

        return $model;
    }
}
