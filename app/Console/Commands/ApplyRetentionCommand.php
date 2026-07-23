<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CardWebhook;
use App\Models\LoginHistory;
use App\Models\SecurityEvent;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

/**
 * Data retention (Wave 7): prune high-volume, non-financial telemetry beyond the
 * retention window. Deliberately NEVER touches the immutable audit log, the ledger,
 * or compliance records — those are retained per policy. Scheduled weekly.
 *
 * Only TERMINAL webhook rows are pruned (delivered/failed outbound deliveries,
 * processed/ignored/failed inbound card webhooks) so nothing in-flight is lost.
 */
class ApplyRetentionCommand extends Command
{
    protected $signature = 'poisapay:retention {--days=90}';

    protected $description = 'Prune login history, acknowledged security events and settled webhook records older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $logins = LoginHistory::where('created_at', '<', $cutoff)->delete();
        $events = SecurityEvent::whereNotNull('acknowledged_at')->where('created_at', '<', $cutoff)->delete();

        // Webhook telemetry — keep pending/in-flight rows; drop settled ones past the window.
        $deliveries = WebhookDelivery::whereIn('status', ['delivered', 'failed'])
            ->where('created_at', '<', $cutoff)->delete();
        $cardHooks = CardWebhook::whereIn('status', ['processed', 'ignored', 'failed'])
            ->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$logins} login-history, {$events} acknowledged security-event, {$deliveries} webhook-delivery and {$cardHooks} card-webhook row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
