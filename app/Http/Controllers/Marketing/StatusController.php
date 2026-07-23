<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Public "System Status" page (linked from the marketing footer). Reports live
 * health for the primary datastore and cache, and derives each product service's
 * status from those signals plus feature flags — no external status provider.
 */
class StatusController extends Controller
{
    public function __invoke(): View
    {
        $db = $this->probe(fn () => DB::select('select 1'));
        $cache = $this->probe(function () {
            Cache::put('status:ping', '1', 5);

            return Cache::get('status:ping') === '1';
        });

        // Every money path rides on the primary database.
        $core = $db ? 'operational' : 'down';

        $rows = [
            [__('Web & Mobile App'), __('Dashboard, sign-in and account pages'), $db ? 'operational' : 'degraded'],
            [__('API'), __('Programmatic access for partners'), $core],
            [__('Wallet & Transfers'), __('Balances and instant peer transfers'), $core],
            [__('Deposits'), __('Incoming crypto deposits and crediting'), $core],
            [__('Withdrawals'), __('Outgoing withdrawals and settlement'), $core],
            [__('Currency Exchange'), __('In-app swaps between assets'), $core],
            [__('Card Issuing'), __('Virtual card creation and authorizations'), $core],
            [__('P2P Marketplace'), __('Buy and sell USDT with other users'), feature('p2p_enabled', false) ? $core : 'maintenance'],
            [__('Database'), __('Primary transactional datastore'), $db ? 'operational' : 'down'],
            [__('Cache & Queues'), __('Background jobs and real-time updates'), $cache ? 'operational' : 'degraded'],
        ];

        $components = array_map(fn ($r) => ['name' => $r[0], 'description' => $r[1], 'status' => $r[2]], $rows);

        $statuses = array_column($components, 'status');
        $overall = match (true) {
            in_array('down', $statuses, true) => 'down',
            in_array('degraded', $statuses, true) => 'degraded',
            in_array('maintenance', $statuses, true) => 'maintenance',
            default => 'operational',
        };

        return view('marketing.status', [
            'components' => $components,
            'overall' => $overall,
            'checkedAt' => now(),
        ]);
    }

    /** Run a health probe, treating any thrown error as a failed check. */
    private function probe(callable $check): bool
    {
        try {
            return (bool) ($check() ?? true);
        } catch (Throwable) {
            return false;
        }
    }
}
