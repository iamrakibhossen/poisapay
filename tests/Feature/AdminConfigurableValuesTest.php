<?php

declare(strict_types=1);

use App\Domain\Compliance\ComplianceListService;
use App\Domain\Risk\RiskEngine;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\KycTier;
use App\Enums\RiskLevel;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Support\KycPolicy;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
});

// ── Phase 1: KYC tier withdrawal ceiling ────────────────────────────────────

it('enforces the per-tier daily withdrawal ceiling (previously dead code)', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Basic]);
    creditUser($user, $this->asset, '2000000000'); // 2,000 USDT

    // Basic default ceiling is $1,000 — a 1,100 USDT cash-out must be blocked.
    app(RequestWithdrawalAction::class)->execute(
        $user, $this->asset, Money::ofBase('1100000000', 6, 'USDT'), 'TdestBasic', 'wd:ceiling'
    );
})->throws(ValidationException::class);

it('lets an admin raise the tier ceiling from settings', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Basic]);
    creditUser($user, $this->asset, '2000000000');

    updateSetting('kyc_basic_daily_withdrawal_ceiling', 0, 'kyc'); // 0 = unlimited

    $w = app(RequestWithdrawalAction::class)->execute(
        $user, $this->asset, Money::ofBase('1100000000', 6, 'USDT'), 'TdestBasic', 'wd:raised'
    );

    expect($w->status)->toBeInstanceOf(WithdrawalStatus::class);
});

it('reads card-issuing eligibility from settings with the enum as fallback', function () {
    expect(KycPolicy::canIssueCard(KycTier::Basic))->toBeFalse()
        ->and(KycPolicy::canIssueCard(KycTier::Full))->toBeTrue();

    updateSetting('kyc_basic_can_issue_card', true, 'kyc');

    expect(KycPolicy::canIssueCard(KycTier::Basic))->toBeTrue();
});

// ── Phase 2: Risk scoring bands from settings ───────────────────────────────

it('bands the risk score using admin-configurable thresholds', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $amount = Money::ofBase('100000000', 6, 'USDT'); // 100 USDT, below auto-approve limit

    // Fresh account (+20) + new destination (+10) = 30 -> Medium by default (>=25).
    $default = app(RiskEngine::class)->scoreWithdrawal($user, $amount, 'TnewAddr');
    expect($default->score)->toBe(30)
        ->and($default->level)->toBe(RiskLevel::Medium)
        ->and($default->level->requiresManualReview())->toBeTrue();

    // Raise the Medium band above 30 -> same score now bands as Low (auto-approve).
    updateSetting('risk_band_medium', 31, 'risk');
    $relaxed = app(RiskEngine::class)->scoreWithdrawal($user, $amount, 'TnewAddr');
    expect($relaxed->score)->toBe(30)->and($relaxed->level)->toBe(RiskLevel::Low);
});

// ── Phase 3: high-risk countries from settings ──────────────────────────────

it('sources high-risk countries from settings, config as fallback', function () {
    expect(ComplianceListService::highRiskCountries())->toContain('KP'); // config fallback

    updateSetting('security_high_risk_countries', ['BD'], 'compliance');

    expect(ComplianceListService::highRiskCountries())->toBe(['BD']);
});
