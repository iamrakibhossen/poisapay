<?php

declare(strict_types=1);

namespace App\Card;

use App\Card\Contracts\CardProviderInterface;
use App\Card\DTOs\CardIssueRequest;
use App\Card\DTOs\ProviderHealth;
use App\Card\DTOs\SpendControlData;
use App\Card\Enums\CardProviderDriver;
use App\Card\Enums\ProviderCapability;
use App\Card\Exceptions\CardProviderException;
use App\Card\Exceptions\FeatureNotSupportedException;
use App\Card\Support\CardholderMapper;
use App\Card\Support\ProviderLogger;
use App\Domain\Audit\ActivityLogger;
use App\Enums\CardNetwork;
use App\Enums\CardStatus;
use App\Enums\CardType;
use App\Models\Card;
use App\Models\CardProvider;
use App\Models\ProviderAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Single entry point controllers/domain use to reach card providers. Exposes only
 * neutral DTOs and our own models; a missing capability degrades gracefully (local
 * state stays authoritative) rather than breaking the operation.
 */
class CardService
{
    public function __construct(
        private readonly CardManager $manager,
        private readonly ProviderLogger $logger,
    ) {}

    public function manager(): CardManager
    {
        return $this->manager;
    }

    public function driver(CardProviderDriver|string|null $key = null): CardProviderInterface
    {
        return $this->manager->driver($key);
    }

    public function forCard(Card $card): CardProviderInterface
    {
        return $this->manager->forCard($card);
    }

    public function forProvider(CardProvider $provider): CardProviderInterface
    {
        return $this->manager->forProvider($provider);
    }

    public function issueCard(User $user, CardProvider $provider, CardType $type, ?string $nickname = null): Card
    {
        if (! $provider->is_active) {
            throw new RuntimeException('This card provider is not active.');
        }
        if ($type === CardType::Virtual && ! $provider->supports_virtual) {
            throw new RuntimeException('This provider does not issue virtual cards.');
        }
        if ($type === CardType::Physical && ! $provider->supports_physical) {
            throw new RuntimeException('This provider does not issue physical cards.');
        }

        $adapter = $this->forProvider($provider);

        // Provider (external) calls happen OUTSIDE the DB transaction — a local failure
        // must never roll back and orphan a provider cardholder/card. ensureCardholder
        // commits its own provider_accounts row so re-issuance always reuses the token.
        $cardholderRef = $this->ensureCardholder($user, $provider, $adapter);

        $request = new CardIssueRequest(
            cardholderRef: $cardholderRef,
            type: $type,
            program: $provider->slug,
            network: CardNetwork::from($provider->network),
            currency: $provider->settlement_currency,
            nickname: $nickname,
        );

        $data = $type === CardType::Physical
            ? $adapter->createPhysicalCard($request)
            : $adapter->createVirtualCard($request);

        return DB::transaction(function () use ($user, $provider, $type, $nickname, $adapter, $cardholderRef, $data): Card {
            $card = Card::create([
                'user_id' => $user->id,
                'card_provider_id' => $provider->id,
                'program' => $provider->slug,
                'type' => $type,
                'network' => $provider->network,
                'issuer_card_ref' => $data->providerCardRef,
                'cardholder_ref' => $cardholderRef,
                'last4' => $data->last4,
                'exp_month' => $data->expMonth,
                'exp_year' => $data->expYear,
                'status' => CardStatus::Inactive,
                'settlement_currency' => $provider->settlement_currency,
                'daily_limit' => '500000',
                'per_tx_limit' => '200000',
                'nickname' => $nickname,
            ]);

            $this->logger->record([
                'card_provider_id' => $provider->id,
                'driver' => $adapter->key(),
                'card_id' => $card->id,
                'direction' => 'outbound',
                'operation' => $type === CardType::Physical ? 'createPhysicalCard' : 'createVirtualCard',
                'success' => true,
                'response' => $data->raw,
            ]);

            ActivityLogger::log('card.generated', $card, ['provider' => $provider->slug, 'driver' => $adapter->key()]);

            return $card;
        });
    }

    public function freeze(Card $card): Card
    {
        $this->tryProvider($card, fn (CardProviderInterface $p) => $p->freezeCard($card->issuer_card_ref));
        $card->update(['status' => CardStatus::Frozen]);
        ActivityLogger::log('card.frozen', $card);

        return $card->refresh();
    }

    public function unfreeze(Card $card): Card
    {
        $this->tryProvider($card, fn (CardProviderInterface $p) => $p->unfreezeCard($card->issuer_card_ref));
        $card->update(['status' => CardStatus::Active]);
        ActivityLogger::log('card.unfrozen', $card);

        return $card->refresh();
    }

    public function syncControls(Card $card): void
    {
        $this->tryProvider($card, fn (CardProviderInterface $p) => $p->setSpendControls(
            $card->issuer_card_ref,
            new SpendControlData(
                dailyLimitMinor: $card->daily_limit !== null ? (string) $card->daily_limit : null,
                perTxLimitMinor: $card->per_tx_limit !== null ? (string) $card->per_tx_limit : null,
                currency: $card->settlement_currency,
                onlineEnabled: (bool) $card->online_enabled,
                atmEnabled: (bool) $card->atm_enabled,
                contactlessEnabled: (bool) $card->contactless_enabled,
                allowedCountries: $card->allowed_countries ?? [],
                blockedMccs: $card->blocked_mccs ?? [],
            ),
        ));
    }

    public function terminate(Card $card, string $reason = 'terminated'): void
    {
        $this->tryProvider($card, fn (CardProviderInterface $p) => $p->terminateCard($card->issuer_card_ref, $reason));
    }

    /** @return list<ProviderCapability> */
    public function capabilities(CardProvider $provider): array
    {
        return $this->forProvider($provider)->capabilities();
    }

    /** @return array<string, ProviderHealth> */
    public function health(): array
    {
        $out = [];
        foreach ($this->manager->factory()->availableDrivers() as $key) {
            try {
                $out[$key] = $this->driver($key)->healthCheck();
            } catch (\Throwable $e) {
                $out[$key] = ProviderHealth::down($key, $e->getMessage());
            }
        }

        return $out;
    }

    private function ensureCardholder(User $user, CardProvider $provider, CardProviderInterface $adapter): string
    {
        $account = ProviderAccount::where('user_id', $user->id)
            ->where('card_provider_id', $provider->id)
            ->first();

        if ($account) {
            return $account->provider_ref;
        }

        try {
            $result = $adapter->createCardholder(CardholderMapper::fromUser($user));
            $ref = $result->providerRef;
            $status = $result->status;
        } catch (FeatureNotSupportedException) {
            $ref = 'usr_'.$user->getKey();
            $status = null;
        }

        ProviderAccount::create([
            'user_id' => $user->id,
            'card_provider_id' => $provider->id,
            'driver' => $adapter->key(),
            'provider_ref' => $ref,
            'status' => $status,
        ]);

        return $ref;
    }

    private function tryProvider(Card $card, callable $fn): void
    {
        try {
            $fn($this->forCard($card));
        } catch (CardProviderException) {
            // Best-effort provider sync: an unsupported op or a provider-side state
            // error (e.g. "card is canceled") must not break the local operation —
            // our card/ledger state stays authoritative.
        }
    }
}
