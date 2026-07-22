<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\LedgerReportService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin financial reports (DollarHub structure — controller + Blade, not
 * Livewire). Read-only ledger reports; the trial-balance CSV is a GET download.
 */
class FinancialReportController extends Controller
{
    public function index(): View
    {
        $this->authorizeReports();

        $service = app(LedgerReportService::class);

        $trialBalance = $service->trialBalance();
        $incomeStatement = $service->incomeStatement();
        $solvency = $service->solvency();

        $assetCount = collect($trialBalance['rows'])->pluck('asset')->unique()->count();
        $solventCount = collect($solvency)->where('solvent', true)->count();

        return view('admin.reports', [
            'trialBalance' => $trialBalance,
            'incomeStatement' => $incomeStatement,
            'solvency' => $solvency,
            'assetCount' => $assetCount,
            'solventCount' => $solventCount,
            'canExport' => $this->canExport(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('export-reports') || $admin->hasRole('super-admin'), 403);

        $tb = app(LedgerReportService::class)->trialBalance();

        ActivityLogger::log('report.exported', null, ['report' => 'trial_balance']);

        return response()->streamDownload(function () use ($tb): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Account type', 'Asset', 'Debit', 'Credit', 'Balance']);
            foreach ($tb['rows'] as $row) {
                fputcsv($out, [$row['type'], $row['asset'], $row['debit'], $row['credit'], $row['balance']]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Balanced', $tb['balanced'] ? 'YES' : 'NO']);
            fputcsv($out, ['Total debit (base)', $tb['total_debit']]);
            fputcsv($out, ['Total credit (base)', $tb['total_credit']]);
            fclose($out);
        }, 'trial-balance.csv', ['Content-Type' => 'text/csv']);
    }

    private function authorizeReports(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-reports') || $admin->hasRole('super-admin'), 403);
    }

    private function canExport(): bool
    {
        $admin = auth('admin')->user();

        return $admin->can('export-reports') || $admin->hasRole('super-admin');
    }
}
