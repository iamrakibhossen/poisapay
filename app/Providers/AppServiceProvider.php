<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Compliance\Contracts\ScreeningProvider;
use App\Domain\Compliance\TravelRule\Contracts\TravelRuleProvider;
use App\Domain\Custody\ChainRoutingAddressDeriver;
use App\Domain\Custody\Contracts\AddressDeriver;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\EnvSeedSignerKeyProvider;
use App\Domain\Exchange\CachingRateProvider;
use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Kyc\Contracts\KycProvider;
use App\Domain\Ramp\Contracts\PayoutProcessor;
use App\Domain\Security\Contracts\GeoLocator;
use App\Domain\Security\Contracts\IpReputationProvider;
use App\Models\Admin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
    ];

    public function register(): void
    {
        // Provider adapters resolve by driver key from config/providers.php, so a
        // real vendor is a one-line swap there — no call-site changes anywhere.
        $this->bindDriver(ScreeningProvider::class, 'screening');
        $this->bindDriver(KycProvider::class, 'kyc');
        $this->bindDriver(PayoutProcessor::class, 'payout');
        $this->bindDriver(IpReputationProvider::class, 'ip_reputation');
        $this->bindDriver(GeoLocator::class, 'geo');
        $this->bindDriver(TravelRuleProvider::class, 'travel_rule');
        $this->bindDriver(BlockchainProvider::class, 'blockchain');

        // Rates: resolve the configured feed, then wrap it in a short-TTL cache.
        $this->app->singleton(RateProvider::class, function ($app): RateProvider {
            $driver = config('providers.rates.driver', 'stub');
            $inner = $app->make(config("providers.rates.drivers.$driver"));
            $ttl = (int) config('providers.rates.cache_ttl', 60);

            return $ttl > 0 ? new CachingRateProvider($inner, $ttl) : $inner;
        });
    }

    /**
     * Bind an interface to the class named by config("providers.$group.driver").
     *
     * @param  class-string  $abstract
     */
    private function bindDriver(string $abstract, string $group): void
    {
        $this->app->singleton($abstract, function ($app) use ($group): object {
            $driver = config("providers.$group.driver");
            $class = config("providers.$group.drivers.$driver");

            return $app->make($class);
        });
    }

    public function boot(): void
    {
        // Financial models must never silently drop attributes on save.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        $this->configureRateLimiters();

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

    /**
     * Named API rate limiters (Wave 4). Limits are operator-tunable via settings;
     * keyed per authenticated user, falling back to IP for guests.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(
            (int) getSetting('rate_api_per_min', 120)
        )->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(
            (int) getSetting('rate_sensitive_per_min', 20)
        )->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute((int) getSetting('rate_auth_per_min', 10))->by($request->ip()),
            Limit::perMinute((int) getSetting('rate_auth_per_email', 5))->by((string) $request->input('email')),
        ]);
    }
}
