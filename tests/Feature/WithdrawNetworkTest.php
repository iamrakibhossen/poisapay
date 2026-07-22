<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Enums\KycTier;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
});

/** USDT on a second chain (Ethereum), with system accounts warmed. */
function usdtOnEthereum(): Asset
{
    $eth = Chain::firstOrCreate(['key' => 'ethereum'], ['name' => 'Ethereum', 'native_symbol' => 'ETH', 'min_confirmations' => 12, 'is_evm' => true]);
    $asset = Asset::firstOrCreate(
        ['symbol' => 'USDT', 'chain_id' => $eth->id, 'contract_address' => 'USDT_ETH'],
        ['name' => 'Tether', 'kind' => 'crypto', 'decimals' => 6, 'is_stablecoin' => true, 'is_active' => true],
    );
    app(AccountResolver::class)->ensureSystemAccounts($asset->id);

    return $asset;
}

it('groups a coin across its funded networks and resolves a chosen network', function () {
    $usdtTron = testAsset('USDT', 6, 'tron');
    $usdtEth = usdtOnEthereum();
    creditUser($this->user, $usdtTron, '5000000');
    creditUser($this->user, $usdtEth, '3000000');

    // Step 1: coin grid shows the coin with its network count.
    actingAs($this->user)->get(route('withdraw'))
        ->assertOk()
        ->assertSee('USDT')
        ->assertSee('2 networks');

    // Step 2: the network list shows both chains.
    actingAs($this->user)->get(route('withdraw', ['coin' => 'USDT']))
        ->assertOk()
        ->assertSee('Tron')
        ->assertSee('Ethereum');

    // Step 3: the chosen network resolves and renders its detail form.
    actingAs($this->user)->get(route('withdraw', ['coin' => 'USDT', 'asset' => $usdtEth->id]))
        ->assertOk()
        ->assertSee('Ethereum network');
});

it('withdraws on a network, reserving funds (available -> locked)', function () {
    $usdt = testAsset('USDT', 6, 'tron');
    creditUser($this->user, $usdt, '5000000');

    actingAs($this->user)->post(route('withdraw.submit'), [
        'assetId' => $usdt->id, 'toAddress' => 'TdestAddress123', 'amount' => '2',
    ])->assertRedirect(route('withdraw'))->assertSessionHas('success');

    // 2 USDT reserved (available 5 → 3).
    expect($this->ledger->availableBalance($this->user, $usdt->id)->baseString())->toBe('3000000');
});
