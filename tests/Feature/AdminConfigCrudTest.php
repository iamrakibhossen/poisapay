<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Asset;
use App\Models\CardProvider;
use App\Models\Currency;
use App\Models\DepositMethod;
use App\Models\WithdrawalMethod;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

    $this->admin = Admin::create(['name' => 'Op', 'email' => 'cfg@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->admin->syncRoles(['super-admin']);

    $this->fiat = Asset::firstOrCreate(
        ['symbol' => 'BDT', 'chain_id' => null, 'contract_address' => null],
        ['name' => 'Taka', 'kind' => 'fiat', 'currency_code' => 'BDT', 'decimals' => 2, 'is_active' => true],
    );
});

// ── Page loads ──────────────────────────────────────────────────────────────

it('loads every config-crud page for an operator', function () {
    foreach (['assets', 'deposit-methods', 'withdrawal-methods', 'card-providers'] as $route) {
        actingAs($this->admin, 'admin')->get(route("admin.{$route}"))->assertOk();
    }
});

// ── Assets ──────────────────────────────────────────────────────────────────

it('creates and updates a coin, syncing its networks', function () {
    // Create the coin.
    actingAs($this->admin, 'admin')->post(route('admin.currencies.save'), [
        'symbol' => 'ABC', 'name' => 'Alpha Coin', 'kind' => 'crypto', 'currency_code' => '',
        'sort' => 0, 'is_active' => '1',
    ])->assertRedirect(route('admin.assets'));

    $coin = Currency::where('symbol', 'ABC')->first();
    expect($coin)->not->toBeNull()->and($coin->name)->toBe('Alpha Coin');

    // Add a network (per-chain deployment) under it.
    $chainId = App\Models\Chain::first()->id;
    actingAs($this->admin, 'admin')->post(route('admin.assets.save'), [
        'currency_id' => $coin->id, 'chain_id' => (string) $chainId, 'contract_address' => '0xABCDEF',
        'decimals' => 6, 'sort' => 0, 'withdrawal_min' => '0', 'withdrawal_fee' => '0', 'is_active' => '1',
    ])->assertRedirect(route('admin.assets'));

    $asset = Asset::where('currency_id', $coin->id)->first();
    expect($asset)->not->toBeNull()
        ->and($asset->symbol)->toBe('ABC')          // identity inherited from the coin
        ->and($asset->contract_address)->toBe('0xABCDEF');

    // Renaming the coin re-syncs the network's denormalised symbol/name.
    actingAs($this->admin, 'admin')->post(route('admin.currencies.save'), [
        'id' => $coin->id, 'symbol' => 'ABC', 'name' => 'Alpha Renamed', 'kind' => 'crypto',
        'sort' => 0, 'is_active' => '1',
    ])->assertRedirect(route('admin.assets'));

    expect($asset->fresh()->name)->toBe('Alpha Renamed');
});

it('rejects a duplicate native coin on a chain with a friendly error', function () {
    $native = Asset::whereNull('contract_address')->whereNotNull('chain_id')->first();
    $coin = Currency::firstOrCreate(['symbol' => 'DUPZ'], ['name' => 'Dup Native', 'kind' => 'crypto']);

    actingAs($this->admin, 'admin')->post(route('admin.assets.save'), [
        'currency_id' => $coin->id, 'chain_id' => (string) $native->chain_id, 'contract_address' => '',
        'decimals' => 18, 'sort' => 0, 'withdrawal_min' => '0', 'withdrawal_fee' => '0', 'is_active' => '1',
    ])->assertSessionHasErrors('chain_id');

    expect(Asset::where('currency_id', $coin->id)->exists())->toBeFalse();
});

it('rejects a duplicate token contract on a chain with a friendly error', function () {
    $token = Asset::whereNotNull('contract_address')->whereNotNull('chain_id')->first();
    $coin = Currency::firstOrCreate(['symbol' => 'DUPT'], ['name' => 'Dup Token', 'kind' => 'crypto']);

    actingAs($this->admin, 'admin')->post(route('admin.assets.save'), [
        'currency_id' => $coin->id, 'chain_id' => (string) $token->chain_id,
        'contract_address' => $token->contract_address,
        'decimals' => 6, 'sort' => 0, 'withdrawal_min' => '0', 'withdrawal_fee' => '0', 'is_active' => '1',
    ])->assertSessionHasErrors('contract_address');

    expect(Asset::where('currency_id', $coin->id)->exists())->toBeFalse();
});

it('toggles an asset active flag', function () {
    $asset = Asset::where('is_active', true)->first();

    actingAs($this->admin, 'admin')->post(route('admin.assets.toggle', $asset->id))
        ->assertRedirect(route('admin.assets'));

    expect($asset->fresh()->is_active)->toBeFalse();
});

// ── Deposit methods ─────────────────────────────────────────────────────────

it('creates and updates a deposit method with details', function () {
    actingAs($this->admin, 'admin')->post(route('admin.deposit-methods.save'), [
        'asset_id' => $this->fiat->id, 'name' => 'ACME Wire', 'type' => 'bank',
        'instructions' => 'Wire it', 'min_amount' => '10', 'percent_fee_bps' => '0', 'sort' => '0', 'is_active' => '1',
        'details' => ['key' => ['Account no.', ''], 'value' => ['123456', '']],
    ])->assertRedirect(route('admin.deposit-methods'));

    $m = DepositMethod::where('name', 'ACME Wire')->first();
    expect($m)->not->toBeNull()
        ->and($m->type->value)->toBe('bank')
        ->and($m->details['Account no.'])->toBe('123456')
        ->and($m->is_active)->toBeTrue();

    actingAs($this->admin, 'admin')->post(route('admin.deposit-methods.save'), [
        'id' => $m->id, 'asset_id' => $this->fiat->id, 'name' => 'ACME Renamed', 'type' => 'bank',
        'percent_fee_bps' => '0', 'sort' => '0', 'is_active' => '1',
    ])->assertRedirect(route('admin.deposit-methods'));

    expect($m->fresh()->name)->toBe('ACME Renamed');
});

it('toggles a deposit method active flag', function () {
    $m = DepositMethod::create([
        'asset_id' => $this->fiat->id, 'name' => 'Rail', 'type' => 'bank', 'details' => [],
        'min_amount' => '0', 'percent_fee_bps' => 0, 'is_active' => true, 'sort' => 0,
    ]);

    actingAs($this->admin, 'admin')->post(route('admin.deposit-methods.toggle', $m->id))
        ->assertRedirect(route('admin.deposit-methods'));

    expect($m->fresh()->is_active)->toBeFalse();
});

it('toggles deposit-enabled on an asset', function () {
    $before = (bool) $this->fiat->deposit_enabled;

    actingAs($this->admin, 'admin')->post(route('admin.deposit-methods.deposit-enabled', $this->fiat->id))
        ->assertRedirect(route('admin.deposit-methods', ['tab' => 'assets']));

    expect((bool) $this->fiat->fresh()->deposit_enabled)->toBe(! $before);
});

// ── Withdrawal methods ──────────────────────────────────────────────────────

it('creates and updates a withdrawal method', function () {
    actingAs($this->admin, 'admin')->post(route('admin.withdrawal-methods.save'), [
        'asset_id' => $this->fiat->id, 'name' => 'bKash', 'type' => 'mobile',
        'number_label' => 'bKash number', 'min_amount' => '100', 'percent_fee_bps' => '0', 'sort' => '0', 'is_active' => '1',
    ])->assertRedirect(route('admin.withdrawal-methods'));

    $m = WithdrawalMethod::where('name', 'bKash')->first();
    expect($m)->not->toBeNull()
        ->and($m->type)->toBe('mobile')
        ->and($m->details['number_label'])->toBe('bKash number')
        ->and($m->is_active)->toBeTrue();

    actingAs($this->admin, 'admin')->post(route('admin.withdrawal-methods.save'), [
        'id' => $m->id, 'asset_id' => $this->fiat->id, 'name' => 'Nagad', 'type' => 'mobile',
        'percent_fee_bps' => '0', 'sort' => '0', 'is_active' => '1',
    ])->assertRedirect(route('admin.withdrawal-methods'));

    expect($m->fresh()->name)->toBe('Nagad');
});

it('toggles a withdrawal method active flag', function () {
    $m = WithdrawalMethod::create([
        'asset_id' => $this->fiat->id, 'name' => 'Rail', 'type' => 'bank', 'details' => [],
        'min_amount' => '0', 'fixed_fee' => '0', 'percent_fee_bps' => 0, 'is_active' => true, 'sort' => 0,
    ]);

    actingAs($this->admin, 'admin')->post(route('admin.withdrawal-methods.toggle', $m->id))
        ->assertRedirect(route('admin.withdrawal-methods'));

    expect($m->fresh()->is_active)->toBeFalse();
});

// ── Card providers ──────────────────────────────────────────────────────────

it('creates and updates a card provider', function () {
    actingAs($this->admin, 'admin')->post(route('admin.card-providers.save'), [
        'name' => 'Acme Issuer', 'slug' => 'acme-issuer', 'driver' => 'marqeta', 'network' => 'visa',
        'settlement_currency' => 'usd', 'supports_virtual' => '1', 'is_active' => '1',
    ])->assertRedirect(route('admin.card-providers'));

    $p = CardProvider::where('slug', 'acme-issuer')->first();
    expect($p)->not->toBeNull()
        ->and($p->settlement_currency)->toBe('USD')
        ->and($p->driver)->toBe(\App\Card\Enums\CardProviderDriver::Marqeta)
        ->and($p->is_demo)->toBeFalse()
        ->and($p->supports_virtual)->toBeTrue();

    actingAs($this->admin, 'admin')->post(route('admin.card-providers.save'), [
        'id' => $p->id, 'name' => 'Acme Renamed', 'slug' => 'acme-issuer', 'driver' => 'marqeta', 'network' => 'mastercard',
        'settlement_currency' => 'USD', 'is_active' => '1',
    ])->assertRedirect(route('admin.card-providers'));

    expect($p->fresh()->name)->toBe('Acme Renamed')
        ->and($p->fresh()->network)->toBe('mastercard');
});

it('toggles a card provider active flag', function () {
    $p = CardProvider::create([
        'name' => 'Toggle Co', 'slug' => 'toggle-co', 'network' => 'visa',
        'settlement_currency' => 'USD', 'supports_virtual' => true, 'is_active' => true, 'is_demo' => true,
    ]);

    actingAs($this->admin, 'admin')->post(route('admin.card-providers.toggle', $p->id))
        ->assertRedirect(route('admin.card-providers'));

    expect($p->fresh()->is_active)->toBeFalse();
});

// ── Authorization gate ──────────────────────────────────────────────────────

it('forbids an operator without the manage-assets permission', function () {
    $plain = Admin::create(['name' => 'Plain', 'email' => 'plain-cfg@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);

    foreach (['assets', 'deposit-methods', 'withdrawal-methods', 'card-providers'] as $route) {
        actingAs($plain, 'admin')->get(route("admin.{$route}"))->assertForbidden();
    }
});
