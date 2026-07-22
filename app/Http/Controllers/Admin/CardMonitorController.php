<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Card\CardService;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCardWebhookJob;
use App\Models\CardProvider;
use App\Models\CardProviderLog;
use App\Models\CardWebhook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Operator monitoring for the card provider layer: outbound/inbound API logs,
 * webhook deliveries (with retry), and live provider health. Read gated by
 * `view-cards`; retry requires `manage-cards`.
 */
class CardMonitorController extends Controller
{
    public function logs(Request $request): View
    {
        $this->authorizeView();

        $driver = (string) $request->query('driver', 'all');
        $direction = (string) $request->query('direction', 'all');
        $result = (string) $request->query('result', 'all');
        $search = (string) $request->query('search', '');

        $logs = CardProviderLog::query()
            ->when($driver !== 'all', fn ($q) => $q->where('driver', $driver))
            ->when($direction !== 'all', fn ($q) => $q->where('direction', $direction))
            ->when($result !== 'all', fn ($q) => $q->where('success', $result === 'ok'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('operation', 'like', '%'.$search.'%')
                ->orWhere('endpoint', 'like', '%'.$search.'%')
                ->orWhere('error', 'like', '%'.$search.'%')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.card-logs', [
            'logs' => $logs,
            'drivers' => $this->drivers(),
            'driver' => $driver,
            'direction' => $direction,
            'result' => $result,
            'search' => $search,
            'stats' => [
                'total' => CardProviderLog::count(),
                'failures' => CardProviderLog::where('success', false)->where('created_at', '>=', Carbon::now()->subDay())->count(),
                'today' => CardProviderLog::where('created_at', '>=', Carbon::now()->startOfDay())->count(),
            ],
        ]);
    }

    public function webhooks(Request $request): View
    {
        $this->authorizeView();

        $driver = (string) $request->query('driver', 'all');
        $status = (string) $request->query('status', 'all');
        $search = (string) $request->query('search', '');

        $webhooks = CardWebhook::query()
            ->when($driver !== 'all', fn ($q) => $q->where('driver', $driver))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('event_type', 'like', '%'.$search.'%')
                ->orWhere('provider_event_id', 'like', '%'.$search.'%')
                ->orWhere('provider_tx_ref', 'like', '%'.$search.'%')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.card-webhooks', [
            'webhooks' => $webhooks,
            'drivers' => $this->drivers(),
            'driver' => $driver,
            'status' => $status,
            'search' => $search,
            'canManage' => $this->canManage(),
            'stats' => [
                'pending' => CardWebhook::where('status', 'pending')->count(),
                'failed' => CardWebhook::where('status', 'failed')->count(),
                'processed' => CardWebhook::where('status', 'processed')->where('processed_at', '>=', Carbon::now()->startOfDay())->count(),
            ],
        ]);
    }

    public function retryWebhook(string $id): RedirectResponse
    {
        abort_unless($this->canManage(), 403);

        $webhook = CardWebhook::findOrFail($id);
        $webhook->update(['status' => 'pending', 'error' => null]);
        ProcessCardWebhookJob::dispatch($webhook->id);

        return back()->with('success', 'Webhook re-queued for processing.');
    }

    public function health(CardService $cards): View
    {
        $this->authorizeView();

        $health = $cards->health();

        $capabilities = [];
        foreach (array_keys($health) as $key) {
            try {
                $capabilities[$key] = array_map(fn ($c) => $c->value, $cards->driver($key)->capabilities());
            } catch (\Throwable) {
                $capabilities[$key] = [];
            }
        }

        return view('admin.card-health', [
            'health' => $health,
            'capabilities' => $capabilities,
            'default' => $cards->manager()->factory()->defaultDriver(),
            'providers' => CardProvider::orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    private function authorizeView(): void
    {
        abort_unless(auth('admin')->user()?->can('view-cards') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }

    private function canManage(): bool
    {
        return (bool) (auth('admin')->user()?->can('manage-cards') || auth('admin')->user()?->hasRole('super-admin'));
    }

    /** @return list<string> */
    private function drivers(): array
    {
        return array_keys((array) config('card.providers', []));
    }
}
