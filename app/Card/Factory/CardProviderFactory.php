<?php

declare(strict_types=1);

namespace App\Card\Factory;

use App\Card\Contracts\CardProviderInterface;
use App\Card\Enums\CardProviderDriver;
use App\Card\Exceptions\CardProviderException;
use Illuminate\Contracts\Container\Container;

/** Resolves a driver key to its adapter from config/card.php; memoised per request. */
class CardProviderFactory
{
    /** @var array<string, CardProviderInterface> */
    private array $resolved = [];

    public function __construct(private readonly Container $app) {}

    public function driver(CardProviderDriver|string|null $key = null): CardProviderInterface
    {
        $key = $key instanceof CardProviderDriver ? $key->value : ($key ?? $this->defaultDriver());

        return $this->resolved[$key] ??= $this->build($key);
    }

    public function defaultDriver(): string
    {
        return (string) config('card.default_provider', 'mock');
    }

    /** @return list<string> */
    public function availableDrivers(): array
    {
        return array_keys((array) config('card.providers', []));
    }

    private function build(string $key): CardProviderInterface
    {
        /** @var array<string, mixed>|null $config */
        $config = config("card.providers.{$key}");

        if (! is_array($config) || empty($config['driver'])) {
            throw new CardProviderException("Card provider [{$key}] is not configured.");
        }

        $class = $config['driver'];

        if (! class_exists($class)) {
            throw new CardProviderException("Card provider driver [{$class}] for [{$key}] does not exist.");
        }

        $provider = $this->app->makeWith($class, ['key' => $key, 'config' => $config]);

        if (! $provider instanceof CardProviderInterface) {
            throw new CardProviderException("Driver [{$class}] must implement CardProviderInterface.");
        }

        return $provider;
    }
}
