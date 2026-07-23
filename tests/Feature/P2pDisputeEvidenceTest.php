<?php

declare(strict_types=1);

use App\Domain\P2p\AddDisputeEvidenceAction;
use App\Domain\P2p\CreateOrderAction;
use App\Domain\P2p\MarkBuyerPaidAction;
use App\Domain\P2p\OpenDisputeAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\P2pDisputeStatus;
use App\Models\Admin;
use App\Models\P2pAd;
use App\Models\P2pDisputeEvidence;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

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
});

it('lets a party upload evidence, stored on the private disk', function () {
    Storage::fake('local');
    $this->actingAs($this->buyer);

    $this->post(route('p2p.dispute.evidence.add', $this->order), [
        'note' => 'bank receipt',
        'file' => UploadedFile::fake()->image('proof.jpg'),
    ])->assertRedirect();

    $ev = P2pDisputeEvidence::where('dispute_id', $this->dispute->id)->first();
    expect($ev)->not->toBeNull()
        ->and($ev->uploader_role)->toBe('buyer')
        ->and($ev->note)->toBe('bank receipt');
    Storage::disk('local')->assertExists($ev->path);
});

it('blocks a non-party from adding evidence', function () {
    $stranger = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->actingAs($stranger);

    $this->post(route('p2p.dispute.evidence.add', $this->order), [
        'file' => UploadedFile::fake()->image('x.jpg'),
    ])->assertForbidden();
});

it('authorises evidence download for a party but not a stranger', function () {
    Storage::fake('local');
    $ev = app(AddDisputeEvidenceAction::class)->execute(
        $this->dispute, 'buyer', (string) $this->buyer->id, UploadedFile::fake()->image('proof.jpg'), null,
    );

    $this->actingAs($this->seller)->get(route('p2p.dispute.evidence', $ev))->assertOk();

    $stranger = User::factory()->create(['kyc_tier' => KycTier::Full, 'kyc_status' => KycStatus::Approved]);
    $this->actingAs($stranger)->get(route('p2p.dispute.evidence', $ev))->assertForbidden();
});

it('lets an operator take the case and renders the case page', function () {
    $admin = Admin::create(['name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->syncRoles(['super-admin']);
    $this->actingAs($admin, 'admin');

    $this->post(route('admin.p2p-disputes.assign', $this->dispute))->assertRedirect();
    expect($this->dispute->refresh()->status)->toBe(P2pDisputeStatus::UnderReview)
        ->and($this->dispute->assigned_admin_id)->toBe($admin->id);

    $this->get(route('admin.p2p-disputes.show', $this->dispute))->assertOk()->assertSee('not received');
});
