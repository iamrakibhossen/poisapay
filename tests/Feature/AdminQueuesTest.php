<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\EntryStatus;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\WithdrawalStatus;
use App\Models\Admin;
use App\Models\JournalEntry;
use App\Models\KycProfile;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Roles/permissions live on the admin guard; an operator with super-admin holds them all.
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);

    $this->admin = Admin::create([
        'name' => 'Op', 'email' => 'ops@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->admin->syncRoles(['super-admin']);
});

/** A withdrawal that lands in manual review with funds locked (reserve-before-sign). */
function reviewWithdrawal($test): Withdrawal
{
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    creditUser($user, $test->asset, '100000000000'); // 100k USDT available

    // A large withdrawal on a fresh account forces WithdrawalStatus::Review.
    return app(RequestWithdrawalAction::class)->execute(
        $user, $test->asset, Money::ofBase('60000000000', 6, 'USDT'), 'TdestReview', 'wd-review'
    );
}

it('loads each money queue page for an operator', function () {
    actingAs($this->admin, 'admin')->get(route('admin.withdrawals'))->assertOk();
    actingAs($this->admin, 'admin')->get(route('admin.kyc'))->assertOk();
    actingAs($this->admin, 'admin')->get(route('admin.ledger'))->assertOk();
});

it('approves a withdrawal in review — status flips and the ledger lock is kept', function () {
    $w = reviewWithdrawal($this);
    expect($w->status)->toBe(WithdrawalStatus::Review);

    $lockedBefore = $this->ledger->lockedBalance($w->user_id, $w->asset_id)->baseString();

    actingAs($this->admin, 'admin')
        ->post(route('admin.withdrawals.approve', $w->id))
        ->assertRedirect();

    expect($w->fresh()->status)->toBe(WithdrawalStatus::Approved)
        ->and($w->fresh()->approved_by)->toBe($this->admin->id)
        // Approval hands off to the signer — funds stay locked, not released.
        ->and($this->ledger->lockedBalance($w->user_id, $w->asset_id)->baseString())->toBe($lockedBefore);
});

it('cancels a withdrawal in review — status flips and the locked funds are released', function () {
    $w = reviewWithdrawal($this);

    $availableBefore = $this->ledger->availableBalance($w->user_id, $w->asset_id)->baseString();
    expect($this->ledger->lockedBalance($w->user_id, $w->asset_id)->baseString())->not->toBe('0');

    actingAs($this->admin, 'admin')
        ->post(route('admin.withdrawals.cancel', $w->id))
        ->assertRedirect();

    expect($w->fresh()->status)->toBe(WithdrawalStatus::Cancelled)
        // locked -> available: the full reserve (amount + fee) is returned.
        ->and($this->ledger->lockedBalance($w->user_id, $w->asset_id)->baseString())->toBe('0')
        ->and($this->ledger->availableBalance($w->user_id, $w->asset_id)->baseString())
        ->toBe(bcadd($availableBefore, '60000000000'));
});

it('shows the KYC review page with details and streams a document image', function () {
    Illuminate\Support\Facades\Storage::fake('local');
    $user = User::factory()->create(['name' => 'Jane KYC', 'kyc_status' => KycStatus::Pending]);

    // Store a fake selfie the reviewer can view.
    $path = Illuminate\Http\UploadedFile::fake()->image('selfie.jpg')->store('kyc/'.$user->id, 'local');

    $profile = KycProfile::create([
        'user_id' => $user->id, 'requested_tier' => KycTier::Full, 'status' => KycStatus::Pending,
        'document_type' => 'passport', 'document_number' => 'P1234567', 'full_name' => 'Jane KYC',
        'country' => 'BD', 'document_paths' => ['front' => $path, 'selfie' => $path],
    ]);

    actingAs($this->admin, 'admin')->get(route('admin.kyc.show', $profile->id))
        ->assertOk()
        ->assertSee('Jane KYC')
        ->assertSee('P1234567')
        ->assertSee('Documents');

    // The reviewer can stream the private document; an unknown/missing slot 404s.
    actingAs($this->admin, 'admin')->get(route('admin.kyc.file', ['id' => $profile->id, 'slot' => 'selfie']))->assertOk();
    actingAs($this->admin, 'admin')->get(route('admin.kyc.file', ['id' => $profile->id, 'slot' => 'back']))->assertNotFound();
});

it('approves a KYC profile — profile and user move to approved at the requested tier', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Unverified, 'kyc_status' => KycStatus::Pending]);
    $profile = KycProfile::create([
        'user_id' => $user->id,
        'requested_tier' => KycTier::Full,
        'status' => KycStatus::Pending,
        'document_type' => 'passport',
        'document_number' => 'P1234567',
    ]);

    actingAs($this->admin, 'admin')
        ->post(route('admin.kyc.approve', $profile->id))
        ->assertRedirect();

    expect($profile->fresh()->status)->toBe(KycStatus::Approved)
        ->and($user->fresh()->kyc_status)->toBe(KycStatus::Approved)
        ->and($user->fresh()->kyc_tier)->toBe(KycTier::Full);
});

it('rejects a KYC profile with a reason', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Unverified, 'kyc_status' => KycStatus::Pending]);
    $profile = KycProfile::create([
        'user_id' => $user->id,
        'requested_tier' => KycTier::Full,
        'status' => KycStatus::Pending,
    ]);

    actingAs($this->admin, 'admin')
        ->post(route('admin.kyc.reject', $profile->id), ['rejectReason' => 'Document unreadable'])
        ->assertRedirect();

    expect($profile->fresh()->status)->toBe(KycStatus::Rejected)
        ->and($profile->fresh()->rejection_reason)->toBe('Document unreadable')
        ->and($user->fresh()->kyc_status)->toBe(KycStatus::Rejected);
});

it('reverses a ledger entry — posts a balanced reversing entry and marks the original reversed', function () {
    $user = User::factory()->create();
    creditUser($user, $this->asset, '5000000');

    $entry = JournalEntry::where('type', 'test.credit')->latest()->firstOrFail();
    expect($entry->status)->not->toBe(EntryStatus::Reversed);

    actingAs($this->admin, 'admin')
        ->post(route('admin.ledger.reverse', $entry->id), ['reason' => 'Duplicate posting'])
        ->assertRedirect();

    $reversal = JournalEntry::where('reverses_entry_id', $entry->id)->first();

    expect($entry->fresh()->status)->toBe(EntryStatus::Reversed)
        ->and($reversal)->not->toBeNull()
        // A reversal returns the credited balance to zero (money conserved).
        ->and($this->ledger->availableBalance($user, $this->asset->id)->baseString())->toBe('0');
});
