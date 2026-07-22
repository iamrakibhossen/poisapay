<?php

declare(strict_types=1);

use App\Domain\Compliance\RaiseAlertAction;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\KycTier;
use App\Enums\RiskLevel;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

    $this->operator = Admin::create([
        'name' => 'Op', 'email' => 'compliance-op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->operator->syncRoles(['super-admin']);

    $this->customer = User::factory()->create(['kyc_tier' => KycTier::Full, 'name' => 'Case Subject']);
});

/** Raise a Medium alert (opens an Open case) and return [alert, case]. */
function raiseComplianceAlert(User $user): array
{
    $alert = app(RaiseAlertAction::class)->execute($user, 'velocity', RiskLevel::Medium, 30, ['high_velocity'], 'withdrawal');

    return [$alert->refresh(), $alert->case];
}

it('loads the compliance page for an operator', function () {
    actingAs($this->operator, 'admin')->get(route('admin.compliance'))->assertOk();
});

it('loads each tab via query string', function (string $tab) {
    actingAs($this->operator, 'admin')
        ->get(route('admin.compliance', ['tab' => $tab]))
        ->assertOk();
})->with(['alerts', 'cases', 'screening']);

it('clears an alert, transitioning its status', function () {
    [$alert] = raiseComplianceAlert($this->customer);

    actingAs($this->operator, 'admin')
        ->post(route('admin.compliance.alert.clear', $alert->id), ['clearNote' => 'False positive.'])
        ->assertRedirect(route('admin.compliance', ['tab' => 'alerts']))
        ->assertSessionHas('success');

    expect($alert->fresh()->status)->toBe(AlertStatus::Cleared)
        ->and($alert->fresh()->resolved_by)->toBe($this->operator->id)
        ->and($alert->fresh()->resolution_note)->toBe('False positive.');
});

it('escalates an alert, transitioning its status', function () {
    [$alert] = raiseComplianceAlert($this->customer);

    actingAs($this->operator, 'admin')
        ->post(route('admin.compliance.alert.escalate', $alert->id))
        ->assertRedirect(route('admin.compliance', ['tab' => 'alerts']))
        ->assertSessionHas('success');

    expect($alert->fresh()->status)->toBe(AlertStatus::Escalated);
});

it('assigns a case to the acting operator', function () {
    [, $case] = raiseComplianceAlert($this->customer);

    actingAs($this->operator, 'admin')
        ->post(route('admin.compliance.alert.assign', $case->id))
        ->assertRedirect(route('admin.compliance', ['tab' => 'cases']))
        ->assertSessionHas('success');

    expect($case->fresh()->assigned_to)->toBe($this->operator->id)
        ->and($case->fresh()->status)->toBe(CaseStatus::Investigating);
});

it('files a SAR against a case', function () {
    [, $case] = raiseComplianceAlert($this->customer);

    actingAs($this->operator, 'admin')
        ->post(route('admin.compliance.case.sar', $case->id), [
            'sarReference' => 'SAR-2026-0042',
            'sarSummary' => 'Structuring pattern.',
        ])
        ->assertRedirect(route('admin.compliance', ['tab' => 'cases']))
        ->assertSessionHas('success');

    expect($case->fresh()->sar_filed)->toBeTrue()
        ->and($case->fresh()->sar_reference)->toBe('SAR-2026-0042')
        ->and($case->fresh()->status)->toBe(CaseStatus::Investigating);
});

it('closes a case, clearing its open alerts', function () {
    [$alert, $case] = raiseComplianceAlert($this->customer);

    actingAs($this->operator, 'admin')
        ->post(route('admin.compliance.case.close', $case->id), ['closeResolution' => 'No suspicious activity confirmed.'])
        ->assertRedirect(route('admin.compliance', ['tab' => 'cases']))
        ->assertSessionHas('success');

    expect($case->fresh()->status)->toBe(CaseStatus::Closed)
        ->and($case->fresh()->resolution)->toBe('No suspicious activity confirmed.')
        ->and($alert->fresh()->status)->toBe(AlertStatus::Cleared);
});

it('validates the close resolution is required', function () {
    [, $case] = raiseComplianceAlert($this->customer);

    actingAs($this->operator, 'admin')
        ->post(route('admin.compliance.case.close', $case->id), ['closeResolution' => ''])
        ->assertSessionHasErrors('closeResolution');

    expect($case->fresh()->status)->not->toBe(CaseStatus::Closed);
});

it('forbids a non-permitted operator', function () {
    $plain = Admin::create([
        'name' => 'Plain', 'email' => 'plain-op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs($plain, 'admin')->get(route('admin.compliance'))->assertForbidden();

    [$alert] = raiseComplianceAlert($this->customer);
    actingAs($plain, 'admin')
        ->post(route('admin.compliance.alert.clear', $alert->id), ['clearNote' => 'x'])
        ->assertForbidden();
});
