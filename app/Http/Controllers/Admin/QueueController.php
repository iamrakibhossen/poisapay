<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;
use Throwable;

/**
 * Queue — "Are background jobs healthy?" Read-only view of pending queue depth
 * and the failed-jobs table (with retry/forget available via Horizon). Mirrors
 * the queue signals SystemHealthController already reads.
 */
class QueueController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-system-health') || $admin->hasRole('super-admin'), 403);

        try {
            $pending = Queue::size();
        } catch (Throwable) {
            $pending = 0;
        }

        $failed = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get(['uuid', 'connection', 'queue', 'exception', 'failed_at']);

        return view('admin.queue', [
            'pending' => $pending,
            'failedCount' => DB::table('failed_jobs')->count(),
            'failed' => $failed,
            'horizonUrl' => url('/horizon'),
        ]);
    }
}
