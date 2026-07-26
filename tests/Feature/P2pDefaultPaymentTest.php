<?php

declare(strict_types=1);

use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\P2pPaymentMethod;
use App\Models\P2pUserPaymentMethod;
use App\Models\User;

beforeEach(function () {
    updateSetting('p2p_enabled', true);
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->bkash = P2pPaymentMethod::where('key', 'bkash')->first();
    $this->nagad = P2pPaymentMethod::where('key', 'nagad')->first();
    $this->actingAs($this->user);
});

function addAccount(P2pPaymentMethod $method): void
{
    test()->post(route('p2p.payment-methods.store'), [
        'payment_method_id' => $method->id,
        'label' => $method->name,
        'account' => ['account_name' => 'Test', 'account_number' => '01700000000'],
    ]);
}

it('marks the first saved account as default', function () {
    addAccount($this->bkash);

    $first = P2pUserPaymentMethod::where('user_id', $this->user->id)->first();
    expect($first->is_default)->toBeTrue();

    addAccount($this->nagad);
    $second = P2pUserPaymentMethod::where('user_id', $this->user->id)->where('payment_method_id', $this->nagad->id)->first();
    expect($second->is_default)->toBeFalse(); // only the first stays default
});

it('switches the default to another account exclusively', function () {
    addAccount($this->bkash);
    addAccount($this->nagad);
    $nagadAcc = P2pUserPaymentMethod::where('user_id', $this->user->id)->where('payment_method_id', $this->nagad->id)->first();

    $this->post(route('p2p.payment-methods.default', $nagadAcc))->assertRedirect();

    $defaults = P2pUserPaymentMethod::where('user_id', $this->user->id)->where('is_default', true)->get();
    expect($defaults)->toHaveCount(1)
        ->and($defaults->first()->id)->toBe($nagadAcc->id);
});

it('promotes a replacement default when the default is deleted', function () {
    addAccount($this->bkash);
    addAccount($this->nagad);
    $bkashAcc = P2pUserPaymentMethod::where('user_id', $this->user->id)->where('payment_method_id', $this->bkash->id)->first();

    $this->delete(route('p2p.payment-methods.destroy', $bkashAcc))->assertRedirect();

    $remaining = P2pUserPaymentMethod::where('user_id', $this->user->id)->get();
    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->is_default)->toBeTrue();
});

it('forbids setting a default on an account you do not own', function () {
    addAccount($this->bkash);
    $mine = P2pUserPaymentMethod::where('user_id', $this->user->id)->first();

    $stranger = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->actingAs($stranger)
        ->post(route('p2p.payment-methods.default', $mine))
        ->assertForbidden();
});
