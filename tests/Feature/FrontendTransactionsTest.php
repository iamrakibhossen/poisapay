<?php

declare(strict_types=1);

use App\Models\Deposit;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
});

it('renders the transactions page server-side', function () {
    Deposit::create([
        'user_id' => $this->user->id, 'asset_id' => $this->usdt->id, 'source' => 'manual',
        'amount' => '5000000', 'confirmations' => 0, 'required_confirmations' => 0, 'status' => 'credited',
    ]);

    actingAs($this->user)->get(route('transactions'))
        ->assertOk()
        ->assertSee('Transactions')
        ->assertSee('Deposit');
});

it('filters the activity by type via query string', function () {
    Deposit::create([
        'user_id' => $this->user->id, 'asset_id' => $this->usdt->id, 'source' => 'manual',
        'amount' => '5000000', 'confirmations' => 0, 'required_confirmations' => 0, 'status' => 'credited',
    ]);

    // Deposits filter shows it; withdrawals filter hides it.
    actingAs($this->user)->get(route('transactions', ['type' => 'deposits']))->assertOk()->assertSee('Deposit');
    actingAs($this->user)->get(route('transactions', ['type' => 'withdrawals']))->assertOk()->assertSee('No transactions');
});

it('requires authentication', function () {
    $this->get(route('transactions'))->assertRedirect(route('login'));
});
