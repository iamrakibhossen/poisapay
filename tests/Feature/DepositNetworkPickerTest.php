<?php

declare(strict_types=1);

use App\Models\Asset;
use App\Models\Chain;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);
    $this->user = User::factory()->create();
});

it('shows the coin grid with a merged multi-network USDT entry', function () {
    actingAs($this->user)->get(route('deposit.index'))
        ->assertOk()
        ->assertSee('Choose a coin')
        ->assertSee('USDT')
        ->assertSee('networks'); // USDT spans many chains → shown as "N networks"
});

it('renders the unified network picker for a coin', function () {
    actingAs($this->user)->get(route('deposit.index', ['symbol' => 'USDT']))
        ->assertOk()
        ->assertSee('Deposit USDT')
        ->assertSee('Network')
        ->assertSee('Ethereum')
        ->assertSee('Tron')
        ->assertSee('Polygon')
        ->assertSee('Select a network above'); // no network chosen yet
});

it('shows the address panel + EVM shared-address hint once a network is chosen', function () {
    $eth = Chain::where('key', 'ethereum')->first();
    $usdt = Asset::where('chain_id', $eth->id)->where('symbol', 'USDT')->first();

    actingAs($this->user)->get(route('deposit.index', ['asset' => $usdt->id]))
        ->assertOk()
        ->assertSee('Deposit USDT')
        ->assertSee('Only send')
        ->assertSee('shared EVM address'); // the EVM hint
});
