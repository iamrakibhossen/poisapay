<?php

declare(strict_types=1);

namespace App\Card;

use App\Card\Contracts\CardProviderInterface;
use App\Card\Enums\CardProviderDriver;
use App\Card\Factory\CardProviderFactory;
use App\Models\Card;
use App\Models\CardProvider;

/** Resolves the adapter for a card/provider by its driver column; falls back to the default. */
class CardManager
{
    public function __construct(private readonly CardProviderFactory $factory) {}

    public function driver(CardProviderDriver|string|null $key = null): CardProviderInterface
    {
        return $this->factory->driver($key);
    }

    public function forProvider(CardProvider $provider): CardProviderInterface
    {
        return $this->factory->driver($provider->driver ?? $this->factory->defaultDriver());
    }

    public function forCard(Card $card): CardProviderInterface
    {
        $provider = $card->relationLoaded('provider') ? $card->provider : $card->provider()->first();

        return $provider ? $this->forProvider($provider) : $this->driver();
    }

    public function factory(): CardProviderFactory
    {
        return $this->factory;
    }
}
