<?php

declare(strict_types=1);

namespace App\Domain\Compliance;

use App\Domain\Audit\ActivityLogger;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Models\Admin;
use App\Models\AmlAlert;
use App\Models\ComplianceCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Operator workflow over compliance cases & alerts (TDD §10.4). */
class ComplianceCaseService
{
    public function assign(ComplianceCase $case, Admin $to): ComplianceCase
    {
        $case->update([
            'assigned_to' => $to->id,
            'status' => $case->status === CaseStatus::Open ? CaseStatus::Investigating : $case->status,
        ]);
        ActivityLogger::log('compliance.case.assigned', $case, ['assigned_to' => $to->id]);

        return $case->refresh();
    }

    /** Resolve one alert (cleared or escalated) with a note. */
    public function resolveAlert(AmlAlert $alert, AlertStatus $status, ?Admin $by = null, ?string $note = null): AmlAlert
    {
        if ($status === AlertStatus::Open) {
            throw new RuntimeException('Resolving an alert requires a terminal status.');
        }

        $alert->update([
            'status' => $status,
            'resolved_by' => $by?->id,
            'resolved_at' => now(),
            'resolution_note' => $note,
        ]);
        ActivityLogger::log('compliance.alert.'.$status->value, $alert, ['note' => $note]);

        return $alert->refresh();
    }

    /**
     * File a Suspicious Activity Report against the case. Structured fields
     * (activity type, narrative, subject amount) are optional and backward
     * compatible with the original free-text reference + summary call.
     */
    public function fileSar(
        ComplianceCase $case,
        Admin $by,
        string $reference,
        ?string $summary = null,
        ?string $activityType = null,
        ?string $narrative = null,
        ?string $amount = null,
    ): ComplianceCase {
        $case->update([
            'sar_filed' => true,
            'sar_reference' => $reference,
            'sar_activity_type' => $activityType,
            'sar_narrative' => $narrative,
            'sar_amount' => $amount,
            'sar_filed_at' => now(),
            'summary' => $summary ?? $case->summary,
            'status' => CaseStatus::Investigating,
        ]);
        ActivityLogger::log('compliance.sar.filed', $case, [
            'reference' => $reference, 'activity_type' => $activityType, 'by' => $by->id,
        ]);

        return $case->refresh();
    }

    /**
     * Close a case with a resolution. All still-open alerts are cleared under the
     * same decision so nothing is left dangling.
     */
    public function close(ComplianceCase $case, Admin $by, string $resolution): ComplianceCase
    {
        return DB::transaction(function () use ($case, $by, $resolution): ComplianceCase {
            // Clear every non-terminal alert (open or escalated) under this decision.
            $case->alerts()->where('status', '!=', AlertStatus::Cleared->value)->update([
                'status' => AlertStatus::Cleared->value,
                'resolved_by' => $by->id,
                'resolved_at' => now(),
                'resolution_note' => 'Cleared on case close.',
            ]);

            $case->update([
                'status' => CaseStatus::Closed,
                'resolution' => $resolution,
                'closed_at' => now(),
            ]);
            ActivityLogger::log('compliance.case.closed', $case, ['resolution' => $resolution, 'by' => $by->id]);

            return $case->refresh();
        });
    }
}
