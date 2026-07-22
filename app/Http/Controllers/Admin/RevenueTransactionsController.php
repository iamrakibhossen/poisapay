<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Revenue\RevenueService;
use App\Enums\LedgerAccountType;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin revenue transactions (DollarHub structure — controller + Blade, not
 * Livewire). Read-only: every fee credit that makes up the revenue wallet, with
 * query-string-driven filters and a CSV export served from a GET download.
 */
class RevenueTransactionsController extends Controller
{
    public function index(Request $request, RevenueService $revenue): View
    {
        abort_unless(auth('admin')->user()?->can('view-revenue') || auth('admin')->user()?->hasRole('super-admin'), 403);

        return view('admin.revenue-transactions', [
            'rows' => $revenue->transactionsQuery($this->filters($request))->paginate(25)->withQueryString(),
            'feeTypeOptions' => $this->feeTypeOptions(),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'user' => (string) $request->query('user', ''),
            'feeType' => (string) $request->query('feeType', ''),
        ]);
    }

    public function export(Request $request, RevenueService $revenue): StreamedResponse
    {
        abort_unless(auth('admin')->user()?->can('view-revenue') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $filters = $this->filters($request);
        $filename = 'revenue-transactions-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($revenue, $filters): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Transaction ID', 'User', 'Email', 'Fee Type', 'Source', 'Amount', 'Currency', 'Created At']);

            $revenue->transactionsQuery($filters)->chunk(500, function ($rows) use ($out, $revenue) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->entry_id,
                        $row->user_name ?? 'system',
                        $row->user_email ?? '',
                        $revenue->feeTypeLabel($row->account_type, $row->entry_type),
                        Str::headline((string) $row->entry_type),
                        Money::ofBase($row->amount, $row->decimals, $row->symbol)->format(),
                        $row->symbol,
                        $row->created_at,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, string> */
    protected function filters(Request $request): array
    {
        return array_filter([
            'fee_type' => (string) $request->query('feeType', ''),
            'user' => (string) $request->query('user', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ], fn ($v) => $v !== '' && $v !== null);
    }

    /** @return array<string, string> */
    protected function feeTypeOptions(): array
    {
        return [
            LedgerAccountType::FeeIncome->value => 'Service Fee',
            LedgerAccountType::FeeCard->value => 'Card Fee',
            LedgerAccountType::FxSpreadIncome->value => 'FX Margin',
        ];
    }
}
