<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
});

it('renders the dashboard page server-side (no Livewire, no JSON)', function () {
    creditUser($this->user, $this->usdt, '1000000');

    actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total balance')
        ->assertSee('USDT');
});

it('renders the dashboard for a user with no funds', function () {
    actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No funds yet');
});

it('requires authentication for the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
