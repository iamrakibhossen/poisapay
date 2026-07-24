<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\GasWallet;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

    $this->operator = Admin::create([
        'name' => 'Op', 'email' => 'wallets@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->operator->syncRoles(['super-admin']);

    // Fund hot with 25 USDT, then sweep 10 hot -> cold (hot=15, cold=10).
    $asset = Asset::where('symbol', 'USDT')->whereNotNull('chain_id')->firstOrFail();
    $ledger = app(LedgerService::class);
    $accounts = app(AccountResolver::class);
    $hot = $accounts->system(LedgerAccountType::TreasuryHot, $asset->id);
    $cold = $accounts->system(LedgerAccountType::TreasuryCold, $asset->id);
    $liability = $accounts->system(LedgerAccountType::LiabilityUserFunds, $asset->id);

    $ledger->post(new EntryData(
        type: 'test.hot-fund',
        idempotencyKey: 'test:hot-fund:'.$asset->id,
        lines: [
            PostingLine::debit($hot->id, $asset->id, '25000000'),
            PostingLine::credit($liability->id, $asset->id, '25000000'),
        ],
    ));
    $ledger->post(new EntryData(
        type: 'test.sweep-cold',
        idempotencyKey: 'test:sweep-cold:'.$asset->id,
        lines: [
            PostingLine::debit($cold->id, $asset->id, '10000000'),
            PostingLine::credit($hot->id, $asset->id, '10000000'),
        ],
    ));
});

it('renders the hot wallet page with the treasury:hot balance', function () {
    actingAs($this->operator, 'admin')->get(route('admin.hot-wallet'))
        ->assertOk()
        ->assertSee('Hot wallet')
        ->assertSee('15.00'); // 25 - 10 swept to cold
});

it('renders the cold wallet page with the treasury:cold balance and watch addresses', function () {
    $chain = Chain::where('key', 'ethereum')->firstOrFail();
    CustodyXpub::create([
        'chain_id' => $chain->id, 'label' => 'Ledger Cold Vault',
        'xpub' => 'xpub'.str_repeat('A', 107), 'derivation_path' => "m/44'/60'/0'/0",
        'next_index' => 0, 'purpose' => 'cold-watch', 'is_active' => true,
    ]);

    actingAs($this->operator, 'admin')->get(route('admin.cold-wallet'))
        ->assertOk()
        ->assertSee('Cold storage')
        ->assertSee('Ledger Cold Vault')
        ->assertSee('10.00');
});

it('flags a low gas wallet on the hot wallet page', function () {
    $chain = Chain::where('key', 'ethereum')->firstOrFail();
    GasWallet::updateOrCreate(
        ['chain_id' => $chain->id],
        ['address' => '0x'.str_repeat('1', 40), 'balance' => '1', 'min_threshold' => '1000000000000000000', 'is_active' => true],
    );

    actingAs($this->operator, 'admin')->get(route('admin.hot-wallet'))
        ->assertOk()
        ->assertSee('Low');
});

it('blocks operators without treasury permission', function () {
    $viewer = Admin::create([
        'name' => 'Viewer', 'email' => 'viewer@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs($viewer, 'admin')->get(route('admin.hot-wallet'))->assertForbidden();
    actingAs($viewer, 'admin')->get(route('admin.cold-wallet'))->assertForbidden();
});

it('redirects the retired wallets route to hot wallet', function () {
    actingAs($this->operator, 'admin')->get(route('admin.wallets'))
        ->assertRedirect('/admin/hot-wallet');
});
