<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\Wallet\WalletService;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);
});

it('models USDT as one coin with a network per chain', function () {
    $usdt = Currency::where('symbol', 'USDT')->first();

    expect($usdt)->not->toBeNull()
        ->and($usdt->assets()->count())->toBe(3)                 // Tron, Ethereum, BSC
        ->and($usdt->is_stablecoin)->toBeTrue();

    // Every network shares the coin's identity but has its own chain + contract.
    $usdt->assets->each(fn (Asset $a) => expect($a->symbol)->toBe('USDT'));
    expect($usdt->assets->pluck('chain_id')->unique()->count())->toBe(3)
        ->and($usdt->assets->pluck('contract_address')->unique()->count())->toBe(3);
});

it('links every asset to a currency (no orphans)', function () {
    expect(Asset::whereNull('currency_id')->count())->toBe(0)
        ->and(Asset::first()->currency)->toBeInstanceOf(Currency::class);
});

it('renders the admin catalogue grouped by coin', function () {
    $admin = Admin::create(['name' => 'Op', 'email' => 'coins@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->syncRoles(['super-admin']);

    actingAs($admin, 'admin')->get(route('admin.assets'))
        ->assertOk()
        ->assertSee('USDT')
        ->assertSee('3 networks');
});

it('renders the swap page showing each coin once', function () {
    $user = User::factory()->create();

    $res = actingAs($user)->get(route('exchange.index'))->assertOk();

    // USDT appears as one coin option even though it has three network rows.
    expect(substr_count($res->getContent(), '>USDT<'))->toBeLessThanOrEqual(1);
});

it('pools a user balance across a coin\'s networks (RedotPay model)', function () {
    $ledger = app(LedgerService::class);
    $user = User::factory()->create();

    $usdt = Asset::where('symbol', 'USDT')->orderBy('id')->get();
    expect($usdt->count())->toBe(3);

    // Credit the user on two different chains.
    creditUser($user, $usdt[0], '5000000'); // 5 USDT on chain A
    creditUser($user, $usdt[1], '3000000'); // 3 USDT on chain B

    // Every network reports the SAME pooled balance (8 USDT).
    foreach ($usdt as $network) {
        expect($ledger->availableBalance($user, $network->id)->baseString())->toBe('8000000');
    }

    // The wallet lists USDT once, with the pooled balance.
    $wallets = app(WalletService::class)->walletsFor($user);
    $usdtWallet = $wallets->firstWhere(fn ($w) => $w->asset->symbol === 'USDT');
    expect($wallets->where(fn ($w) => $w->asset->symbol === 'USDT')->count())->toBe(1)
        ->and($usdtWallet->available->baseString())->toBe('8000000');
});
