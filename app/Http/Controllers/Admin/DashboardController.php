<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\DepositStatus;
use App\Enums\KycStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\KycProfile;
use App\Models\LedgerLine;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin dashboard (DollarHub structure — controller + Blade, not Livewire).
 * Read-only operational overview with a 14-day deposit chart via window.ppChart.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()->isOperator(), 403);

        // 14-day deposit volume series for the chart.
        $days = collect(range(13, 0))->map(fn ($d) => Carbon::today()->subDays($d));
        $depositsByDay = Deposit::query()
            ->where('created_at', '>=', Carbon::today()->subDays(13))
            ->get()
            ->groupBy(fn ($d) => $d->created_at->toDateString());

        $series = $days->map(fn (Carbon $day) => [
            'label' => $day->format('M j'),
            'value' => $depositsByDay->get($day->toDateString())?->count() ?? 0,
        ]);

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'newUsers7d' => User::where('created_at', '>=', now()->subWeek())->count(),
                'pendingKyc' => KycProfile::where('status', KycStatus::Pending->value)->count(),
                'reviewWithdrawals' => Withdrawal::where('status', WithdrawalStatus::Review->value)->count(),
                'pendingDeposits' => Deposit::whereIn('status', [DepositStatus::Detected->value, DepositStatus::Confirming->value])->count(),
                'depositsToday' => Deposit::whereDate('created_at', today())->count(),
                'transfers24h' => Transfer::where('created_at', '>=', now()->subDay())->count(),
                'journalLines' => LedgerLine::count(),
            ],
            'chartLabels' => $series->pluck('label'),
            'chartValues' => $series->pluck('value'),
            'kycQueue' => KycProfile::with('user')->where('status', KycStatus::Pending->value)->latest()->limit(5)->get(),
            'reviewQueue' => Withdrawal::with('user', 'asset')->where('requires_review', true)
                ->whereIn('status', [WithdrawalStatus::Review->value, WithdrawalStatus::Pending->value])
                ->latest()->limit(5)->get(),
        ]);
    }
}
