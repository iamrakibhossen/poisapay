<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operator monitor for outbound webhooks: subscribed endpoints (per merchant), their
 * delivery health, and the recent delivery log with one-click replay of failures.
 * Read-mostly — mutations are activate/deactivate an endpoint and retry a delivery.
 */
class WebhookEndpointsController extends Controller
{
    public function index(Request $request): View
    {
        $this->guardAccess();

        $status = (string) $request->query('status', 'all');

        $endpoints = WebhookEndpoint::query()
            ->with('user:id,name,email')
            ->withCount([
                'deliveries',
                'deliveries as failed_count' => fn ($q) => $q->where('status', 'failed'),
                'deliveries as delivered_count' => fn ($q) => $q->where('status', 'delivered'),
            ])
            ->latest()
            ->get();

        $deliveries = WebhookDelivery::query()
            ->with('endpoint.user:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->limit(50)
            ->get();

        return view('admin.system.webhooks', [
            'endpoints' => $endpoints,
            'deliveries' => $deliveries,
            'status' => $status,
            'stats' => [
                'endpoints' => $endpoints->count(),
                'active' => $endpoints->where('is_active', true)->count(),
                'pending' => WebhookDelivery::where('status', 'pending')->count(),
                'failed' => WebhookDelivery::where('status', 'failed')->count(),
            ],
        ]);
    }

    public function toggle(string $id): RedirectResponse
    {
        $this->guardManage();

        $endpoint = WebhookEndpoint::findOrFail($id);
        $endpoint->update(['is_active' => ! $endpoint->is_active]);

        ActivityLogger::log('system.webhook.toggle', $endpoint, ['is_active' => $endpoint->is_active]);

        return back()->with('success', $endpoint->is_active ? 'Endpoint activated.' : 'Endpoint deactivated.');
    }

    public function retry(string $id): RedirectResponse
    {
        $this->guardManage();

        $delivery = WebhookDelivery::findOrFail($id);
        $delivery->update(['status' => 'pending', 'next_retry_at' => null]);
        DispatchWebhookJob::dispatch($delivery->id);

        ActivityLogger::log('system.webhook.retry', $delivery, ['event' => $delivery->event]);

        return back()->with('success', 'Delivery re-queued.');
    }

    private function guardAccess(): void
    {
        abort_unless(
            auth('admin')->user()?->can('view-system-health') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }

    private function guardManage(): void
    {
        abort_unless(
            auth('admin')->user()?->can('manage-developer') || auth('admin')->user()?->hasRole('super-admin'),
            403,
        );
    }
}
