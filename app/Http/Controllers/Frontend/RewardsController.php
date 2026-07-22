<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\RewardGrant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rewards & Referrals page — server-rendered. The controller builds the reward
 * totals, grant history and referral list and passes them straight to the Blade
 * view. Pure read; the only client-side logic is a tiny clipboard-copy button.
 */
class RewardsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $grants = RewardGrant::with('asset')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        // Aggregate reward totals per asset symbol for display.
        $totals = $grants
            ->groupBy(fn ($g) => $g->asset?->symbol ?? '—')
            ->map(function ($group) {
                $asset = $group->first()->asset;
                $sum = '0';
                foreach ($group as $g) {
                    $sum = bcadd($sum, (string) ($g->amount ?? '0'), 0);
                }

                return $asset ? $asset->money($sum)->format() : $sum;
            });

        $referrals = Referral::with('referee')
            ->where('referrer_id', $user->id)
            ->latest()
            ->get();

        $referralCode = $user->referral_code;

        return view('frontend.rewards', [
            'referralCode' => $referralCode,
            'shareLink' => $referralCode ? route('register').'?ref='.$referralCode : null,
            'rewardCount' => $grants->count(),
            'referralCount' => $referrals->count(),
            'totals' => $totals
                ->map(fn ($formatted, $symbol) => [
                    'symbol' => $symbol,
                    'formatted' => $formatted,
                ])
                ->values()
                ->all(),
            'grants' => $grants->map(fn (RewardGrant $g) => [
                'type' => str_replace('_', ' ', (string) $g->type),
                'amount' => $g->asset ? $g->asset->money($g->amount)->format() : (string) $g->amount,
                'at_human' => $g->created_at->diffForHumans(),
            ])->all(),
            'referrals' => $referrals->map(fn (Referral $r) => [
                'name' => $r->referee?->name ?? 'Pending sign-up',
                'at_human' => $r->created_at->diffForHumans(),
                'status_label' => $r->status->label(),
                'status_color' => $r->status->color(),
            ])->all(),
        ]);
    }
}
