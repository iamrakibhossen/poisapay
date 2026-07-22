<?php

declare(strict_types=1);

use App\Domain\Compliance\ComplianceCaseService;
use App\Domain\Compliance\RaiseAlertAction;
use App\Domain\Compliance\ScreeningService;
use App\Domain\Kyc\SubmitKycAction;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\KycTier;
use App\Enums\RiskLevel;
use App\Enums\ScreeningStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Admin;
use App\Models\AmlAlert;
use App\Models\ComplianceCase;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full, 'name' => 'Jane Clean']);
});

function withdraw(User $user, $asset, string $base, string $key = 'wd-1'): Withdrawal
{
    return app(RequestWithdrawalAction::class)->execute($user, $asset, Money::ofBase($base, 6, 'USDT'), 'TdestAddr'.$key, $key);
}

it('screens an ordinary user clear', function () {
    $result = app(ScreeningService::class)->screen($this->user, 'onboarding');

    expect($result->result)->toBe(ScreeningStatus::Clear)->and($result->score)->toBe(0);
});

it('opens a compliance case with an alert on a review-worthy withdrawal', function () {
    creditUser($this->user, $this->asset, '100000000000'); // 100k USDT
    withdraw($this->user, $this->asset, '60000000000'); // 60k -> above auto threshold + new account

    $alert = AmlAlert::where('user_id', $this->user->id)->first();
    expect($alert)->not->toBeNull()
        ->and($alert->case_id)->not->toBeNull()
        ->and(ComplianceCase::where('user_id', $this->user->id)->where('status', CaseStatus::Open)->exists())->toBeTrue();
});

it('escalates and forces review on a sanctions denylist hit', function () {
    updateSetting('aml_sanctions_denylist', ['jane clean'], 'compliance');
    creditUser($this->user, $this->asset, '10000000');

    $w = withdraw($this->user, $this->asset, '2000000', 'wd-hit');

    $alert = AmlAlert::where('user_id', $this->user->id)->where('type', 'sanctions_hit')->first();
    expect($alert)->not->toBeNull()
        ->and($alert->severity)->toBe(RiskLevel::Critical)
        ->and($alert->status)->toBe(AlertStatus::Escalated)
        ->and($w->fresh()->status)->toBe(WithdrawalStatus::Review)
        ->and($alert->case->risk_level)->toBe(RiskLevel::Critical);
});

it('flags the watchlist as a review-band screen', function () {
    updateSetting('aml_watchlist', ['jane clean'], 'compliance');

    $result = app(ScreeningService::class)->screen($this->user, 'onboarding');

    expect($result->result)->toBe(ScreeningStatus::Review)->and($result->score)->toBe(80);
});

it('screens onboarding on KYC submission and alerts on a hit', function () {
    updateSetting('aml_sanctions_denylist', ['jane clean'], 'compliance');

    app(SubmitKycAction::class)->execute($this->user, [
        'requested_tier' => KycTier::Full,
        'document_type' => 'nid',
        'full_name' => 'Jane Clean',
        'country' => 'BD',
    ]);

    $alert = AmlAlert::where('user_id', $this->user->id)->where('context', 'onboarding')->first();
    expect($alert)->not->toBeNull()->and($alert->type)->toBe('sanctions_hit');
});

it('de-dupes an identical open alert for the same subject', function () {
    $raise = app(RaiseAlertAction::class);
    $a = $raise->execute($this->user, 'velocity', RiskLevel::Medium, 30, ['high_velocity'], 'withdrawal');
    $b = $raise->execute($this->user, 'velocity', RiskLevel::Medium, 30, ['high_velocity'], 'withdrawal');

    expect($b->id)->toBe($a->id)->and(AmlAlert::where('user_id', $this->user->id)->count())->toBe(1);
});

it('resolves an alert as cleared', function () {
    $admin = Admin::create(['name' => 'Op', 'email' => 'op8@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $alert = app(RaiseAlertAction::class)->execute($this->user, 'velocity', RiskLevel::Medium, 30, [], 'withdrawal');

    app(ComplianceCaseService::class)->resolveAlert($alert, AlertStatus::Cleared, $admin, 'false positive');

    expect($alert->fresh()->status)->toBe(AlertStatus::Cleared)
        ->and($alert->fresh()->resolved_by)->toBe($admin->id);
});

it('files a SAR and closes a case, clearing its open alerts', function () {
    $admin = Admin::create(['name' => 'Op', 'email' => 'op9@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $alert = app(RaiseAlertAction::class)->execute($this->user, 'sanctions_hit', RiskLevel::Critical, 95, [], 'withdrawal');
    $case = $alert->case;
    $service = app(ComplianceCaseService::class);

    $service->fileSar($case, $admin, 'SAR-2026-001', 'Structuring pattern.');
    $service->close($case, $admin, 'Reported to FIU.');

    expect($case->fresh()->sar_filed)->toBeTrue()
        ->and($case->fresh()->sar_reference)->toBe('SAR-2026-001')
        ->and($case->fresh()->status)->toBe(CaseStatus::Closed)
        ->and($alert->fresh()->status)->toBe(AlertStatus::Cleared);
});
