<?php

declare(strict_types=1);

use App\Domain\Compliance\AccountGuard;
use App\Domain\Compliance\ComplianceCaseService;
use App\Domain\Compliance\ComplianceListService;
use App\Domain\Compliance\ScreeningService;
use App\Domain\Compliance\TravelRule\Contracts\TravelRuleProvider;
use App\Domain\Compliance\TravelRule\StubTravelRuleProvider;
use App\Domain\Transfer\ExecuteTransferAction;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\CaseStatus;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\RiskLevel;
use App\Enums\ScreeningStatus;
use App\Models\Admin;
use App\Models\ComplianceCase;
use App\Models\KycProfile;
use App\Models\TravelRuleRecord;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

function cdOperator(): Admin
{
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    $admin = Admin::create(['name' => 'CdOp', 'email' => 'cdop@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $admin->syncRoles(['super-admin']);

    return $admin;
}

// ---------------------------------------------------------------------------
// 5.7 Freeze enforcement at all touchpoints
// ---------------------------------------------------------------------------
it('blocks a frozen account from moving value', function () {
    $asset = testAsset('USDT', 6, 'tron');
    $sender = User::factory()->create(['is_frozen' => true]);
    $recipient = User::factory()->create();
    creditUser($sender, $asset, '10000000');

    app(ExecuteTransferAction::class)->execute($sender, $recipient, $asset, $asset->money('1000000'), 'frozen-t1');
})->throws(RuntimeException::class, 'Account is frozen.');

it('the account guard passes an active user and rejects a frozen one', function () {
    AccountGuard::assertActive(User::factory()->create()); // no throw
    expect(fn () => AccountGuard::assertActive(User::factory()->create(['is_frozen' => true])))
        ->toThrow(RuntimeException::class);
});

// ---------------------------------------------------------------------------
// 5.4 Persistent lists + country risk
// ---------------------------------------------------------------------------
it('screens a persistently denylisted name as a hit', function () {
    $user = User::factory()->create(['name' => 'Bad Actor']);
    app(ComplianceListService::class)->add('denylist', 'name', 'bad actor', 'OFAC SDN', 'ofac');

    $result = app(ScreeningService::class)->screen($user, 'onboarding');
    expect($result->result)->toBe(ScreeningStatus::Hit)->and($result->score)->toBeGreaterThanOrEqual(95);
});

it('escalates a high-risk country to review', function () {
    $user = User::factory()->create(['name' => 'Clean Person']);
    KycProfile::create([
        'user_id' => $user->id, 'requested_tier' => KycTier::Basic, 'status' => KycStatus::Pending, 'country' => 'KP',
    ]);

    $result = app(ScreeningService::class)->screen($user, 'onboarding');
    expect($result->result)->toBe(ScreeningStatus::Review);
});

it('manages list membership + whitelist checks', function () {
    $svc = app(ComplianceListService::class);
    $entry = $svc->add('whitelist', 'address', 'Tgood', 'trusted counterparty');
    expect($svc->isWhitelisted('address', 'Tgood'))->toBeTrue()
        ->and($svc->isDenied('address', 'Tgood'))->toBeFalse();

    $svc->remove($entry->id);
    expect($svc->isWhitelisted('address', 'Tgood'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// 5.6 Structured SAR + export
// ---------------------------------------------------------------------------
it('files a structured SAR with narrative + amount', function () {
    $user = User::factory()->create();
    $case = ComplianceCase::create([
        'user_id' => $user->id, 'status' => CaseStatus::Open, 'risk_level' => RiskLevel::High, 'reason' => 'test',
    ]);
    $admin = Admin::create(['name' => 'A', 'email' => 'a@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);

    app(ComplianceCaseService::class)->fileSar($case, $admin, 'SAR-001', 'summary', 'structuring', 'Rapid in/out pattern', '5000000');

    $case->refresh();
    expect($case->sar_filed)->toBeTrue()
        ->and($case->sar_activity_type)->toBe('structuring')
        ->and($case->sar_narrative)->toBe('Rapid in/out pattern')
        ->and($case->sar_filed_at)->not->toBeNull();
});

it('exports compliance cases as CSV for an operator', function () {
    $admin = cdOperator();
    $response = actingAs($admin, 'admin')->get(route('admin.compliance.export.cases'));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('renders + manages the sanctions list admin page', function () {
    $admin = cdOperator();
    actingAs($admin, 'admin')->get(route('admin.compliance-lists'))->assertOk()->assertSee('Sanctions');

    actingAs($admin, 'admin')->post(route('admin.compliance-lists.store'), [
        'list' => 'denylist', 'kind' => 'name', 'value' => 'Evil Corp', 'reason' => 'sanctioned',
    ])->assertRedirect();
    expect(app(ComplianceListService::class)->isDenied('name', 'Evil Corp'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// 5.5 Travel Rule
// ---------------------------------------------------------------------------
it('resolves the Travel Rule provider to the stub', function () {
    expect(app(TravelRuleProvider::class))->toBeInstanceOf(StubTravelRuleProvider::class);
});

it('captures a Travel Rule record for an on-chain withdrawal above the threshold', function () {
    updateSetting('security_travel_rule', true, 'security');
    updateSetting('security_travel_rule_threshold', 1, 'security'); // display units

    $asset = testAsset('USDT', 6, 'tron');
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($user, $asset, '100000000');

    $w = app(RequestWithdrawalAction::class)->execute($user, $asset, $asset->money('5000000'), 'Tbenef', 'tr-1');

    $record = TravelRuleRecord::where('withdrawal_id', $w->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->status)->toBe('submitted')
        ->and($record->beneficiary_address)->toBe('Tbenef')
        ->and($record->provider)->toBe('stub');
});

it('does not capture a Travel Rule record below the threshold', function () {
    updateSetting('security_travel_rule', true, 'security');
    updateSetting('security_travel_rule_threshold', 1000000, 'security');

    $asset = testAsset('USDT', 6, 'tron');
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($user, $asset, '100000000');

    $w = app(RequestWithdrawalAction::class)->execute($user, $asset, $asset->money('1000000'), 'Tsmall', 'tr-2');

    expect(TravelRuleRecord::where('withdrawal_id', $w->id)->exists())->toBeFalse();
});
