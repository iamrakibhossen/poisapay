<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmlAlert;
use App\Models\ComplianceCase;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Regulator/audit CSV exports of the compliance record (Wave 5): AML alerts and
 * cases (including SAR fields). Streamed so large exports don't buffer in memory.
 */
class ComplianceExportController extends Controller
{
    public function cases(): StreamedResponse
    {
        $this->guard();

        return $this->stream('compliance-cases.csv',
            ['id', 'user_id', 'status', 'risk_level', 'reason', 'sar_filed', 'sar_reference', 'sar_activity_type', 'sar_amount', 'sar_filed_at', 'opened_at', 'closed_at'],
            ComplianceCase::query()->latest()->cursor(),
            fn (ComplianceCase $c) => [
                $c->id, $c->user_id, $c->status->value, $c->risk_level?->value, $c->reason,
                $c->sar_filed ? 'yes' : 'no', $c->sar_reference, $c->sar_activity_type,
                $c->sar_amount, $c->sar_filed_at?->toIso8601String(),
                $c->created_at?->toIso8601String(), $c->closed_at?->toIso8601String(),
            ],
        );
    }

    public function alerts(): StreamedResponse
    {
        $this->guard();

        return $this->stream('aml-alerts.csv',
            ['id', 'user_id', 'type', 'severity', 'status', 'score', 'context', 'case_id', 'created_at'],
            AmlAlert::query()->latest()->cursor(),
            fn (AmlAlert $a) => [
                $a->id, $a->user_id, $a->type, $a->severity->value, $a->status->value,
                $a->score, $a->context, $a->case_id, $a->created_at?->toIso8601String(),
            ],
        );
    }

    /**
     * @param  array<int, string>  $header
     * @param  iterable<int, Model>  $rows
     */
    private function stream(string $filename, array $header, iterable $rows, callable $map): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows, $map) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $map($row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function guard(): void
    {
        abort_unless(
            auth('admin')->user()?->can('view-compliance') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }
}
