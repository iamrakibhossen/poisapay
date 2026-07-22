<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Compliance\ComplianceCaseService;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\RiskLevel;
use App\Enums\ScreeningStatus;
use App\Http\Controllers\Controller;
use App\Models\AmlAlert;
use App\Models\ComplianceCase;
use App\Models\ScreeningResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin AML/compliance case-management workbench (DollarHub structure —
 * controller + Blade, not Livewire). Alerts + cases + sanctions screening,
 * with clear/escalate/assign/file-SAR/close wrapping {@see ComplianceCaseService}.
 */
class ComplianceController extends Controller
{
    public function index(Request $request): View
    {
        $this->guard();

        $tab = (string) $request->query('tab', 'alerts');
        $search = (string) $request->query('search', '');
        $alertStatus = (string) $request->query('alertStatus', 'all');
        $severity = (string) $request->query('severity', 'all');
        $caseStatus = (string) $request->query('caseStatus', 'all');
        $riskLevel = (string) $request->query('riskLevel', 'all');

        $alerts = collect();
        $cases = collect();
        $screenings = collect();

        if ($tab === 'alerts') {
            $alerts = AmlAlert::query()
                ->with(['user', 'case'])
                ->when($alertStatus !== 'all', fn ($q) => $q->where('status', $alertStatus))
                ->when($severity !== 'all', fn ($q) => $q->where('severity', $severity))
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')))
                ->latest()
                ->paginate(25)
                ->withQueryString();
        } elseif ($tab === 'cases') {
            $cases = ComplianceCase::query()
                ->with(['user', 'assignee'])
                ->withCount('alerts')
                ->when($caseStatus !== 'all', fn ($q) => $q->where('status', $caseStatus))
                ->when($riskLevel !== 'all', fn ($q) => $q->where('risk_level', $riskLevel))
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')))
                ->latest()
                ->paginate(25)
                ->withQueryString();
        } else {
            $screenings = ScreeningResult::query()
                ->with('user')
                ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')))
                ->latest()
                ->paginate(25)
                ->withQueryString();
        }

        return view('admin.compliance', [
            'tab' => $tab,
            'search' => $search,
            'alertStatus' => $alertStatus,
            'severity' => $severity,
            'caseStatus' => $caseStatus,
            'riskLevel' => $riskLevel,
            'alerts' => $alerts,
            'cases' => $cases,
            'screenings' => $screenings,
            'stats' => [
                'openAlerts' => AmlAlert::where('status', AlertStatus::Open->value)->count(),
                'escalatedAlerts' => AmlAlert::where('status', AlertStatus::Escalated->value)->count(),
                'openCases' => ComplianceCase::where('status', '!=', CaseStatus::Closed->value)->count(),
                'sarsFiled' => ComplianceCase::where('sar_filed', true)->count(),
            ],
            'severities' => RiskLevel::cases(),
            'screeningStatuses' => ScreeningStatus::cases(),
        ]);
    }

    public function clearAlert(Request $request, string $id): RedirectResponse
    {
        $this->guard();

        $data = $request->validate([
            'clearNote' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $alert = AmlAlert::findOrFail($id);
            app(ComplianceCaseService::class)->resolveAlert(
                $alert,
                AlertStatus::Cleared,
                auth('admin')->user(),
                ($data['clearNote'] ?? '') !== '' ? $data['clearNote'] : null,
            );

            return $this->backToTab('alerts')->with('success', 'Alert cleared.');
        } catch (\Throwable $e) {
            return $this->backToTab('alerts')->with('error', 'Could not clear the alert: '.$e->getMessage());
        }
    }

    public function escalateAlert(Request $request, string $id): RedirectResponse
    {
        $this->guard();

        try {
            $alert = AmlAlert::findOrFail($id);
            app(ComplianceCaseService::class)->resolveAlert(
                $alert,
                AlertStatus::Escalated,
                auth('admin')->user(),
            );

            return $this->backToTab('alerts')->with('success', 'Alert escalated.');
        } catch (\Throwable $e) {
            return $this->backToTab('alerts')->with('error', 'Could not escalate the alert: '.$e->getMessage());
        }
    }

    public function assignAlert(Request $request, string $id): RedirectResponse
    {
        $this->guard();

        try {
            $case = ComplianceCase::findOrFail($id);
            app(ComplianceCaseService::class)->assign($case, auth('admin')->user());

            return $this->backToTab('cases')->with('success', 'Case assigned to you.');
        } catch (\Throwable $e) {
            return $this->backToTab('cases')->with('error', 'Could not assign the case: '.$e->getMessage());
        }
    }

    public function fileSar(Request $request, string $id): RedirectResponse
    {
        $this->guardSar();

        $data = $request->validate([
            'sarReference' => ['required', 'string', 'min:2', 'max:120'],
            'sarSummary' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $case = ComplianceCase::findOrFail($id);
            app(ComplianceCaseService::class)->fileSar(
                $case,
                auth('admin')->user(),
                $data['sarReference'],
                ($data['sarSummary'] ?? '') !== '' ? $data['sarSummary'] : null,
            );

            return $this->backToTab('cases')->with('success', 'SAR filed.');
        } catch (\Throwable $e) {
            return $this->backToTab('cases')->with('error', 'Could not file the SAR: '.$e->getMessage());
        }
    }

    public function closeCase(Request $request, string $id): RedirectResponse
    {
        $this->guard();

        $data = $request->validate([
            'closeResolution' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $case = ComplianceCase::findOrFail($id);
            app(ComplianceCaseService::class)->close($case, auth('admin')->user(), $data['closeResolution']);

            return $this->backToTab('cases')->with('success', 'Case closed.');
        } catch (\Throwable $e) {
            return $this->backToTab('cases')->with('error', 'Could not close the case: '.$e->getMessage());
        }
    }

    protected function guard(): void
    {
        abort_unless(auth('admin')->user()?->can('view-compliance') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }

    protected function guardSar(): void
    {
        abort_unless(auth('admin')->user()?->can('file-sar') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }

    private function backToTab(string $tab): RedirectResponse
    {
        return redirect()->route('admin.compliance', ['tab' => $tab]);
    }
}
