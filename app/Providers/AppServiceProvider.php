<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Custody\ChainRoutingAddressDeriver;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\EnvSeedSignerKeyProvider;
use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Exchange\StubRateProvider;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        // Real TRON derivation when custody is live; simulated for EVM + all tests
        // (routing happens inside ChainRoutingAddressDeriver by chain + config flag).
        AddressDeriver::class => ChainRoutingAddressDeriver::class,
        // Testnet/demo signer keys from an env-encrypted seed; swap for KMS/HSM in prod.
        SignerKeyProvider::class => EnvSeedSignerKeyProvider::class,
        RateProvider::class => StubRateProvider::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Financial models must never silently drop attributes on save.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // super-admin operators bypass individual permission checks.
        Gate::before(function ($user, $ability) {
            if ($user instanceof Admin && $user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });

        // NOTE: listeners in app/Listeners are auto-discovered by their handle*()
        // methods (Laravel event discovery). Do NOT also register them manually here
        // (Event::subscribe/Event::listen) — that double-fires every listener, e.g.
        // duplicate "New KYC submission" operator notifications.
    }
}
