<?php

declare(strict_types=1);

namespace App\Providers;

use App\Card\CardManager;
use App\Card\CardService;
use App\Card\Factory\CardProviderFactory;
use App\Card\Support\ProviderLogger;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/** Wires the provider-agnostic card layer. Adding a provider needs no change here. */
class CardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderLogger::class);

        $this->app->singleton(CardProviderFactory::class, fn (Container $app) => new CardProviderFactory($app));

        $this->app->singleton(CardManager::class, fn (Container $app) => new CardManager($app->make(CardProviderFactory::class)));

        $this->app->singleton(CardService::class, fn (Container $app) => new CardService(
            $app->make(CardManager::class),
            $app->make(ProviderLogger::class),
        ));
    }
}
