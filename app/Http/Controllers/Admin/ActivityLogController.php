<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin activity logs (DollarHub structure — controller + Blade, not Livewire).
 * Read-only audit trail; actor-type filter + search are query-string driven.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('view-activity-logs'), 403);

        $actorType = (string) $request->query('actorType', 'all');
        $search = (string) $request->query('search', '');

        $logs = AuditLog::query()
            ->when($actorType !== 'all', fn ($q) => $q->where('actor_type', $actorType))
            ->when($search !== '', fn ($q) => $q->where(function ($sub) use ($search) {
                $term = '%'.$search.'%';
                $sub->where('action', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('actor_name', 'like', $term);
            }))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.activity-logs', [
            'logs' => $logs,
            'actorType' => $actorType,
            'search' => $search,
            'tabs' => [
                'all' => AuditLog::count(),
                'operator' => AuditLog::where('actor_type', 'operator')->count(),
                'user' => AuditLog::where('actor_type', 'user')->count(),
                'system' => AuditLog::where('actor_type', 'system')->count(),
            ],
        ]);
    }
}
