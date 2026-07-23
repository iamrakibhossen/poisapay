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
});

it('renders the custody wallets page with hot & cold balances', function () {
    $asset = Asset::where('symbol', 'USDT')->whereNotNull('chain_id')->firstOrFail();

    $ledger = app(LedgerService::class);
    $accounts = app(AccountResolver::class);
    $hot = $accounts->system(LedgerAccountType::TreasuryHot, $asset->id);
    $cold = $accounts->system(LedgerAccountType::TreasuryCold, $asset->id);
    $liability = $accounts->system(LedgerAccountType::LiabilityUserFunds, $asset->id);

    // Fund the hot wallet: debit treasury:hot (debit-normal) / credit liability => hot holds 25 USDT.
    $ledger->post(new EntryData(
        type: 'test.hot-fund',
        idempotencyKey: 'test:hot-fund:'.$asset->id,
        lines: [
            PostingLine::debit($hot->id, $asset->id, '25000000'),
            PostingLine::credit($liability->id, $asset->id, '25000000'),
        ],
    ));

    // Sweep 10 USDT hot -> cold.
    $ledger->post(new EntryData(
        type: 'test.sweep-cold',
        idempotencyKey: 'test:sweep-cold:'.$asset->id,
        lines: [
            PostingLine::debit($cold->id, $asset->id, '10000000'),
            PostingLine::credit($hot->id, $asset->id, '10000000'),
        ],
    ));

    actingAs($this->operator, 'admin')->get(route('admin.wallets'))
        ->assertOk()
        ->assertSee('Custody wallets')
        ->assertSee('Hot wallet')
        ->assertSee('Cold storage')
        ->assertSee('15.000000')  // hot: 25 - 10
        ->assertSee('10.000000'); // cold
});

it('shows registered cold-watch addresses and low-gas warnings', function () {
    $chain = Chain::where('key', 'ethereum')->firstOrFail();

    CustodyXpub::create([
        'chain_id' => $chain->id, 'label' => 'Ledger Cold Vault',
        'xpub' => 'xpub'.str_repeat('A', 107), 'derivation_path' => "m/44'/60'/0'/0",
        'next_index' => 0, 'purpose' => 'cold-watch', 'is_active' => true,
    ]);

    GasWallet::updateOrCreate(
        ['chain_id' => $chain->id],
        ['address' => '0x'.str_repeat('1', 40), 'balance' => '1', 'min_threshold' => '1000000000000000000', 'is_active' => true],
    );

    actingAs($this->operator, 'admin')->get(route('admin.wallets'))
        ->assertOk()
        ->assertSee('Ledger Cold Vault')
        ->assertSee('Low');
});

it('blocks operators without treasury permission', function () {
    $viewer = Admin::create([
        'name' => 'Viewer', 'email' => 'viewer@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs($viewer, 'admin')->get(route('admin.wallets'))->assertForbidden();
});
