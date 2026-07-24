<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\Withdrawal;
use Illuminate\View\View;

/**
 * Risk — "Which customers and withdrawals are elevated-risk?" Read-only view of
 * risk-scored withdrawals (from the existing RiskEngine output stored on each
 * withdrawal) and recent security events. No scoring happens here.
 */
class RiskController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->can('view-compliance') || $admin->hasRole('super-admin'), 403);

        $elevated = [RiskLevel::Medium->value, RiskLevel::High->value, RiskLevel::Critical->value];

        return view('admin.risk', [
            'withdrawals' => Withdrawal::with('user', 'asset')
                ->whereIn('risk_level', $elevated)
                ->latest()
                ->paginate(20),
            'events' => SecurityEvent::with('user')->latest()->limit(15)->get(),
            'stats' => [
                'critical' => Withdrawal::where('risk_level', RiskLevel::Critical->value)->count(),
                'high' => Withdrawal::where('risk_level', RiskLevel::High->value)->count(),
                'medium' => Withdrawal::where('risk_level', RiskLevel::Medium->value)->count(),
                'events24h' => SecurityEvent::where('created_at', '>=', now()->subDay())->count(),
            ],
        ]);
    }
}
