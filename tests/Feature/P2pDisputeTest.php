<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\OpenDisputeAction;
use App\Domain\P2p\ResolveDisputeAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pDisputeStatus;
use App\Enums\P2pEscrowStatus;
use App\Enums\P2pOrderStatus;
use App\Models\Admin;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->seller = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->buyer = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    creditUser($this->seller, $this->usdt, '1000000000');
    $this->ad = P2pAd::factory()->create(['user_id' => $this->seller->id, 'asset_id' => $this->usdt->id]);

    $this->order = app(CreateOrderAction::class)->execute($this->buyer, $this->ad, Money::ofDecimal('100', 6, 'USDT'));
    app(MarkBuyerPaidAction::class)->execute($this->order->refresh(), $this->buyer);
    $this->dispute = app(OpenDisputeAction::class)->execute($this->order->refresh(), $this->buyer, 'not received');

    $this->admin = Admin::create(['name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->admin->syncRoles(['super-admin']);
});

it('force-releases the escrow to the buyer when the operator rules for them', function () {
    app(ResolveDisputeAction::class)->execute($this->dispute->refresh(), $this->admin, 'buyer', 'evidence favours buyer');

    $this->order->refresh();
    expect($this->order->status)->toBe(P2pOrderStatus::ForceReleased)
        ->and($this->order->escrow->status)->toBe(P2pEscrowStatus::Released)
        ->and($this->ledger->availableBalance($this->buyer, $this->usdt->id)->baseString())->toBe('100000000')
        ->and($this->dispute->refresh()->status)->toBe(P2pDisputeStatus::ResolvedBuyer);
});

it('force-cancels and refunds + restocks when the operator rules for the seller', function () {
    app(ResolveDisputeAction::class)->execute($this->dispute->refresh(), $this->admin, 'seller');

    $this->order->refresh();
    expect($this->order->status)->toBe(P2pOrderStatus::ForceCancelled)
        ->and($this->ledger->availableBalance($this->seller, $this->usdt->id)->baseString())->toBe('1000000000')
        ->and($this->ad->fresh()->available_amount)->toBe('1000000000')
        ->and($this->dispute->refresh()->status)->toBe(P2pDisputeStatus::ResolvedSeller);
});

it('renders the admin P2P console + settings tab and resolves over HTTP', function () {
    $this->actingAs($this->admin, 'admin');

    $this->get(route('admin.p2p'))->assertOk()->assertSee($this->order->ref);
    $this->get(route('admin.p2p-disputes'))->assertOk()->assertSee('not received');
    $this->get(route('admin.settings', 'p2p'))->assertOk()->assertSee('Taker Fee');

    $this->post(route('admin.p2p-disputes.resolve', $this->dispute), ['winner' => 'buyer'])->assertRedirect();
    expect($this->order->refresh()->status)->toBe(P2pOrderStatus::ForceReleased);
});
