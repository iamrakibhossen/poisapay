<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\User;
use App\Sell\Actions\Seller\SetSellerStatus;
use App\Sell\Actions\Seller\SubmitSellerApplication;
use App\Sell\DTOs\SellerApplicationData;
use App\Sell\Enums\SellerStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Services\SellerService;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->user = User::factory()->create();
    $this->admin = Admin::create([
        'name' => 'Op', 'username' => 'op', 'email' => 'op@poisapay.test',
        'password' => bcrypt('secret'), 'is_active' => true,
    ]);
    $this->sellers = app(SellerService::class);
});

$data = fn () => SellerApplicationData::fromArray([
    'brand_name' => 'Rahim Studios',
    'bio' => 'I sell software and templates.',
    'categories' => ['software', 'templates'],
]);

$apply = fn (User $user, $data) => app(SubmitSellerApplication::class)->execute($user, $data);

it('creates a pending seller + application and audits the event', function () use ($apply, $data) {
    $seller = $apply($this->user, $data());

    expect($seller->status)->toBe(SellerStatus::PendingReview)
        ->and($seller->brand_name)->toBe('Rahim Studios')
        ->and($seller->applications()->where('status', 'pending')->count())->toBe(1)
        ->and($this->sellers->isApprovedSeller($this->user->fresh()))->toBeFalse()
        ->and(AuditLog::where('action', 'sell.seller.applied')->exists())->toBeTrue();
});

it('approves: seller can sell, application marked approved, audited', function () use ($apply, $data) {
    $seller = $apply($this->user, $data());
    expect($this->sellers->isApprovedSeller($this->user))->toBeFalse();

    app(SetSellerStatus::class)->execute($seller, SellerStatus::Approved, $this->admin);

    expect($seller->fresh()->status)->toBe(SellerStatus::Approved)
        ->and($seller->fresh()->approved_at)->not->toBeNull()
        ->and($seller->applications()->first()->status)->toBe('approved')
        // Cache was invalidated automatically by the Seller saved observer.
        ->and($this->sellers->isApprovedSeller($this->user->fresh()))->toBeTrue()
        ->and(AuditLog::where('action', 'sell.seller.approved')->exists())->toBeTrue();
});

it('rejects with a reason and blocks selling', function () use ($apply, $data) {
    $seller = $apply($this->user, $data());

    app(SetSellerStatus::class)->execute($seller, SellerStatus::Rejected, $this->admin, 'Incomplete KYC');

    expect($seller->fresh()->status)->toBe(SellerStatus::Rejected)
        ->and($seller->applications()->first()->status)->toBe('rejected')
        ->and($seller->applications()->first()->notes)->toBe('Incomplete KYC')
        ->and($this->sellers->isApprovedSeller($this->user->fresh()))->toBeFalse();
});

it('lets a rejected user re-apply, but not an approved one', function () use ($apply, $data) {
    $seller = $apply($this->user, $data());
    app(SetSellerStatus::class)->execute($seller, SellerStatus::Rejected, $this->admin);

    $apply($this->user, $data()); // re-apply allowed from rejected
    expect($seller->fresh()->status)->toBe(SellerStatus::PendingReview)
        ->and($seller->fresh()->applications()->count())->toBe(2);

    app(SetSellerStatus::class)->execute($seller->fresh(), SellerStatus::Approved, $this->admin);
    expect(fn () => $apply($this->user, $data()))->toThrow(SellException::class);
});

it('suspending an approved seller blocks selling', function () use ($apply, $data) {
    $seller = $apply($this->user, $data());
    app(SetSellerStatus::class)->execute($seller, SellerStatus::Approved, $this->admin);
    expect($this->sellers->isApprovedSeller($this->user->fresh()))->toBeTrue();

    app(SetSellerStatus::class)->execute($seller->fresh(), SellerStatus::Suspended, $this->admin, 'Chargebacks');
    expect($this->sellers->isApprovedSeller($this->user->fresh()))->toBeFalse();
});

it('refuses applications when the module flag is off', function () use ($apply, $data) {
    updateSetting('sell_enabled', false);
    expect(fn () => $apply($this->user, $data()))->toThrow(SellException::class);
});

it('never treats a user as an approved seller when the flag is off', function () use ($apply, $data) {
    $seller = $apply($this->user, $data());
    app(SetSellerStatus::class)->execute($seller, SellerStatus::Approved, $this->admin);
    expect($this->sellers->isApprovedSeller($this->user->fresh()))->toBeTrue();

    updateSetting('sell_enabled', false);
    expect($this->sellers->isApprovedSeller($this->user->fresh()))->toBeFalse();
});
