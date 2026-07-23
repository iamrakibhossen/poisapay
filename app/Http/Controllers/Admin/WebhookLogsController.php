<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\ActivityLogger;
use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operator viewer for the inbound-webhook request log (populated by the WebhookLogger
 * middleware). List + filter every request our webhook endpoints received, inspect the
 * full payload/headers/response, mark an entry resolved, or delete it. Read-mostly.
 */
class WebhookLogsController extends Controller
{
    public function index(Request $request): View
    {
        $this->guardAccess();

        $provider = (string) $request->query('provider', 'all');
        $state = (string) $request->query('state', 'all');   // all|resolved|unresolved|failed
        $search = trim((string) $request->query('search', ''));

        $logs = WebhookLog::query()
            ->when($provider !== 'all' && $provider !== '', fn ($q) => $q->where('provider', $provider))
            ->when($state === 'resolved', fn ($q) => $q->where('resolved', true))
            ->when($state === 'unresolved', fn ($q) => $q->where('resolved', false))
            ->when($state === 'failed', fn ($q) => $q->where('status', '>=', 400))
            ->when($search !== '', fn ($q) => $q->where('url', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.system.webhook-logs', [
            'logs' => $logs,
            'provider' => $provider,
            'state' => $state,
            'search' => $search,
            'providers' => WebhookLog::query()->select('provider')->distinct()->orderBy('provider')->pluck('provider')->filter()->values(),
            'stats' => [
                'total' => WebhookLog::count(),
                'failed' => WebhookLog::where('status', '>=', 400)->count(),
                'unresolved' => WebhookLog::where('resolved', false)->count(),
            ],
        ]);
    }

    public function show(string $id): View
    {
        $this->guardAccess();

        $log = WebhookLog::findOrFail($id);

        return view('admin.system.webhook-log-show', ['log' => $log]);
    }

    public function resolve(string $id): RedirectResponse
    {
        $this->guardManage();

        $log = WebhookLog::findOrFail($id);
        $log->update(['resolved' => true]);
        ActivityLogger::log('system.webhook-log.resolved', $log, ['provider' => $log->provider, 'route' => $log->route]);

        return back()->with('success', 'Webhook log marked as resolved.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->guardManage();

        $log = WebhookLog::findOrFail($id);
        $log->delete();
        ActivityLogger::log('system.webhook-log.deleted', null, ['id' => $id]);

        return redirect()->route('admin.webhook-logs')->with('success', 'Webhook log deleted.');
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
