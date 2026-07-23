<?php

declare(strict_types=1);

use App\Enums\KycStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create(['kyc_status' => KycStatus::None]);
});

it('redirects the legacy verification URL into the settings tab', function () {
    actingAs($this->user)->get(route('kyc.index'))
        ->assertRedirect(route('settings.index', ['tab' => 'verification']));
});

it('renders the verification section inside settings (no Livewire)', function () {
    actingAs($this->user)->get(route('settings.index', ['tab' => 'verification']))
        ->assertOk()
        ->assertSee('Verification')
        ->assertSee('Identity verification');
});

it('submits a KYC application with documents and redirects', function () {
    Storage::fake('local');

    actingAs($this->user)->post(route('kyc.submit'), [
        'fullName' => 'Rakib Hossen',
        'dateOfBirth' => '1990-01-01',
        'country' => 'BD',
        'address' => '123 Gulshan Ave, Dhaka',
        'documentType' => 'nid',
        'documentNumber' => 'NID-12345',
        'documentFront' => UploadedFile::fake()->image('front.jpg'),
        'selfie' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertRedirect(route('settings.index', ['tab' => 'verification']))->assertSessionHas('success');

    expect($this->user->fresh()->kyc_status)->toBe(KycStatus::Pending);
});

it('validates required document fields', function () {
    actingAs($this->user)->post(route('kyc.submit'), [
        'fullName' => 'Rakib', 'dateOfBirth' => '1990-01-01', 'country' => 'BD',
        'address' => 'Dhaka', 'documentType' => 'nid', 'documentNumber' => 'X',
        // no files
    ])->assertSessionHasErrors(['documentFront', 'selfie']);

    expect($this->user->fresh()->kyc_status)->toBe(KycStatus::None);
});

it('blocks a second application while one is in progress', function () {
    Storage::fake('local');
    $this->user->update(['kyc_status' => KycStatus::Pending]);

    actingAs($this->user)->post(route('kyc.submit'), [
        'fullName' => 'Rakib', 'dateOfBirth' => '1990-01-01', 'country' => 'BD',
        'address' => 'Dhaka', 'documentType' => 'nid', 'documentNumber' => 'X',
        'documentFront' => UploadedFile::fake()->image('f.jpg'),
        'selfie' => UploadedFile::fake()->image('s.jpg'),
    ])->assertSessionHasErrors('documentType');
});

it('requires authentication for the verification page', function () {
    $this->get(route('kyc.index'))->assertRedirect(route('login'));
});
