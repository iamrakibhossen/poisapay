<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
});

it('registers a user and returns a bearer token', function () {
    $res = $this->postJson('/api/v1/auth/register', [
        'name' => 'API User',
        'email' => 'api@poisapay.test',
        'password' => 'password123',
    ]);

    $res->assertCreated()
        ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'token']]);
});

it('returns wallet balances for an authenticated user', function () {
    $user = User::factory()->create();
    creditUser($user, $this->asset, '2500000');
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/wallets');

    $res->assertOk();
    $usdt = collect($res->json('data'))->firstWhere('asset', 'USDT');
    expect($usdt['available'])->toBe('2.500000');
});

it('rejects an unauthenticated wallet request', function () {
    $this->getJson('/api/v1/wallets')->assertUnauthorized();
});

it('performs a transfer via the API', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create(['handle' => 'payee']);
    creditUser($sender, $this->asset, '5000000');
    Sanctum::actingAs($sender);

    $res = $this->postJson('/api/v1/transfers', [
        'recipient' => 'payee',
        'asset' => 'USDT',
        'amount' => '1.5',
    ], ['Idempotency-Key' => 'api-tf-1']);

    $res->assertCreated()->assertJsonPath('data.amount', '1.500000');
});

it('exposes the reference asset list', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/assets')->assertOk()->assertJsonStructure(['data' => [['symbol', 'decimals']]]);
});
