<?php

declare(strict_types=1);

use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\OpenDisputeAction;
use App\Domain\P2p\ResolveDisputeAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\Admin;
use App\Models\P2pAd;
use App\Models\P2pDisputeNote;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    updateSetting('p2p_enabled', true);
    updateSetting('p2p_taker_fee_bps', 0);

    $this->usdt = testAsset('USDT', 6, 'tron');
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

it('notifies both parties when a dispute is resolved', function () {
    app(ResolveDisputeAction::class)->execute($this->dispute->refresh(), $this->admin, 'buyer', 'ruled for buyer');

    $hasResolved = fn (User $u) => $u->notifications()->get()
        ->contains(fn ($n) => ($n->data['event'] ?? null) === 'p2p.dispute.resolved');

    expect($hasResolved($this->buyer))->toBeTrue()
        ->and($hasResolved($this->seller))->toBeTrue();
});

it('lets an operator add an internal note that stays operator-only', function () {
    $this->actingAs($this->admin, 'admin')
        ->post(route('admin.p2p-disputes.notes', $this->dispute), ['body' => 'Buyer proof looks genuine.'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(P2pDisputeNote::where('dispute_id', $this->dispute->id)->where('body', 'Buyer proof looks genuine.')->exists())->toBeTrue();

    // Shown on the admin case page, not on the buyer's order page.
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.p2p-disputes.show', $this->dispute))
        ->assertOk()
        ->assertSee('Buyer proof looks genuine.');

    $this->actingAs($this->buyer)
        ->get(route('p2p.order', $this->order))
        ->assertOk()
        ->assertDontSee('Buyer proof looks genuine.');
});

it('requires manage-p2p to add an internal note', function () {
    $viewer = Admin::create(['name' => 'Viewer', 'email' => 'viewer@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);

    $this->actingAs($viewer, 'admin')
        ->post(route('admin.p2p-disputes.notes', $this->dispute), ['body' => 'nope'])
        ->assertForbidden();

    expect(P2pDisputeNote::where('dispute_id', $this->dispute->id)->exists())->toBeFalse();
});
