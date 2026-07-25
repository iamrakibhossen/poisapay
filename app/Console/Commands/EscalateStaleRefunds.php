<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shop\Actions\Refund\EscalateRefundRequest;
use App\Shop\Enums\RefundRequestStatus;
use App\Shop\Models\RefundRequest;
use Illuminate\Console\Command;

/**
 * Auto-escalates refund requests the seller left unactioned past their response
 * SLA (`sell_refund_sla_days`), so a stalling seller can't trap a buyer's money.
 * Escalated requests surface in the operator refund queue.
 */
class EscalateStaleRefunds extends Command
{
    protected $signature = 'poisapay:shop-escalate-refunds {--limit=200 : Max requests per run}';

    protected $description = 'Escalate refund requests the seller ignored past the response SLA.';

    public function handle(EscalateRefundRequest $escalate): int
    {
        $due = RefundRequest::query()
            ->where('status', RefundRequestStatus::Requested->value)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->with('order')
            ->limit((int) $this->option('limit'))
            ->get();

        $count = 0;
        foreach ($due as $request) {
            $escalate->execute($request);
            $count++;
        }

        $this->info("Escalated {$count} stale refund request(s).");

        return self::SUCCESS;
    }
}
