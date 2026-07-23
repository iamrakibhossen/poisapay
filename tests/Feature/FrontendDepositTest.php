<?php

declare(strict_types=1);

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\DepositMethod;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
    $this->bank = DepositMethod::create([
        'asset_id' => $this->asset->id, 'name' => 'Test Bank', 'type' => 'bank',
        'details' => ['bank_name' => 'City Bank'], 'min_amount' => '1000000', 'is_active' => true, 'sort' => 0,
    ]);
    $this->crypto = DepositMethod::create([
        'asset_id' => $this->asset->id, 'name' => 'USDT (Tron)', 'type' => 'crypto',
        'details' => ['address' => 'TCryptoAddr123', 'network' => 'Tron'], 'min_amount' => '1000000', 'is_active' => true, 'sort' => 1,
    ]);
});

it('renders the deposit currency grid', function () {
    actingAs($this->user)->get(route('deposit.index'))
        ->assertOk()
        ->assertSee('Deposit')
        ->assertSee('USDT');
});

it('renders the deposit method list for a chosen asset', function () {
    actingAs($this->user)->get(route('deposit.index', ['asset' => $this->asset->id]))
        ->assertOk()
        ->assertSee('Test Bank')
        ->assertSee('USDT (Tron)');
});

it('renders a crypto method address with a QR', function () {
    actingAs($this->user)->get(route('deposit.index', ['asset' => $this->asset->id, 'method' => $this->crypto->id]))
        ->assertOk()
        ->assertSee('TCryptoAddr123')
        ->assertSee('<svg', false);
});

it('submits a manual deposit for review (no ledger movement)', function () {
    actingAs($this->user)->post(route('deposit.submit'), [
        'assetId' => $this->asset->id, 'methodId' => $this->bank->id, 'amount' => '5', 'reference' => 'TXN-9',
    ])->assertRedirect()->assertSessionHas('success');

    $deposit = Deposit::where('user_id', $this->user->id)->firstOrFail();
    expect($deposit->status)->toBe(DepositStatus::Detected)
        ->and($deposit->source)->toBe('manual')
        ->and($deposit->reference)->toBe('TXN-9');
});

it('rejects submitting a crypto method (arrives on-chain)', function () {
    actingAs($this->user)->post(route('deposit.submit'), [
        'assetId' => $this->asset->id, 'methodId' => $this->crypto->id, 'amount' => '5',
    ])->assertSessionHasErrors('methodId');
});

it('enforces the method minimum on submit', function () {
    actingAs($this->user)->post(route('deposit.submit'), [
        'assetId' => $this->asset->id, 'methodId' => $this->bank->id, 'amount' => '0.5',
    ])->assertSessionHasErrors('amount');
});

it('requires authentication for the deposit page', function () {
    $this->get(route('deposit.index'))->assertRedirect(route('login'));
});
