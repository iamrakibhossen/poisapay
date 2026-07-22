<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Rewards\ManualGrantAction;
use App\Enums\ReferralStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Referral;
use App\Models\RewardCampaign;
use App\Models\RewardGrant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin rewards (DollarHub structure — controller + Blade, not Livewire).
 * Reward campaigns, manual grants and a read-only referral ledger. Viewing is
 * gated on view-rewards; mutations on manage-rewards. Human amounts are
 * converted to base units against the selected asset's decimals, and manual
 * grants go through the same treasury {@see ManualGrantAction} as before.
 */
class AdminRewardsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()?->can('view-rewards') || auth('admin')->user()?->hasRole('super-admin'), 403);

        $tab = $request->query('tab', 'campaigns');
        $tab = in_array($tab, ['campaigns', 'grants', 'referrals'], true) ? $tab : 'campaigns';

        $grants = $tab === 'grants'
            ? RewardGrant::with(['user', 'asset'])->latest()->paginate(25)->withQueryString()
            : collect();

        $referrals = $tab === 'referrals'
            ? Referral::with(['referrer', 'referee'])->latest()->paginate(25)->withQueryString()
            : collect();

        return view('admin.rewards', [
            'tab' => $tab,
            'campaigns' => $tab === 'campaigns'
                ? RewardCampaign::with('asset')->orderBy('key')->get()
                : collect(),
            'grants' => $grants,
            'referrals' => $referrals,
            'assets' => Asset::where('is_active', true)->orderBy('symbol')->get(),
            'stats' => [
                'activeCampaigns' => RewardCampaign::where('is_active', true)->count(),
                'grants' => RewardGrant::count(),
                'referrals' => Referral::count(),
                'rewardedReferrals' => Referral::where('status', ReferralStatus::Rewarded->value)->count(),
            ],
        ]);
    }

    public function saveCampaign(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $request->merge(['is_active' => $request->boolean('is_active')]);

        $editingId = $request->input('id') ?: null;

        $rules = [
            'key' => 'required|string|max:64|unique:reward_campaigns,key'.($editingId ? ','.$editingId : ''),
            'name' => 'required|string|max:120',
            'type' => 'required|in:fixed,percentage',
            'asset_id' => 'nullable|exists:assets,id',
            'amount' => 'nullable|numeric|min:0',
            'rate_bps' => 'nullable|integer|min:1|max:10000',
            'min_spend' => 'nullable|numeric|min:0',
            'max_reward' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ];

        if ($request->input('type') === 'fixed') {
            $rules['asset_id'] = 'required|exists:assets,id';
            $rules['amount'] = 'required|numeric|gt:0';
        } else {
            $rules['rate_bps'] = 'required|integer|min:1|max:10000';
        }

        $data = $request->validate($rules, [], ['asset_id' => 'asset', 'amount' => 'amount', 'rate_bps' => 'rate']);

        try {
            $asset = ($data['asset_id'] ?? null) ? Asset::find($data['asset_id']) : null;

            $toBase = fn (?string $human): ?string => (($human ?? '') !== '' && $asset)
                ? Money::ofDecimal($human, $asset->decimals, $asset->symbol)->baseString()
                : null;

            $attributes = [
                'key' => $data['key'],
                'name' => $data['name'],
                'type' => $data['type'],
                'asset_id' => ($data['asset_id'] ?? null) ?: null,
                'amount' => $data['type'] === 'fixed' ? $toBase($request->input('amount')) : null,
                'rate_bps' => $data['type'] === 'percentage' ? (int) $data['rate_bps'] : null,
                'min_spend' => $toBase($request->input('min_spend')),
                'max_reward' => $toBase($request->input('max_reward')),
                'is_active' => (bool) $data['is_active'],
                'starts_at' => ($data['starts_at'] ?? null) ?: null,
                'ends_at' => ($data['ends_at'] ?? null) ?: null,
            ];

            // Never pass id=null to create() on a HasUuids model (mass-assignment guard).
            $editingId
                ? tap(RewardCampaign::whereKey($editingId)->firstOrFail())->update($attributes)
                : RewardCampaign::create($attributes);

            return redirect()->route('admin.rewards')
                ->with('success', $editingId ? 'Campaign updated.' : 'Campaign created.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.rewards')
                ->with('error', 'Could not save the campaign: '.$e->getMessage());
        }
    }

    public function toggleCampaign(Request $request, string $id): RedirectResponse
    {
        $this->authorizeManage();

        try {
            $c = RewardCampaign::findOrFail($id);
            $c->update(['is_active' => ! $c->is_active]);

            return redirect()->route('admin.rewards')
                ->with('success', $c->is_active ? 'Campaign activated.' : 'Campaign paused.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.rewards')
                ->with('error', 'Could not update the campaign: '.$e->getMessage());
        }
    }

    public function grant(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'grantEmail' => 'required|email',
            'grantAssetId' => 'required|exists:assets,id',
            'grantAmount' => 'required|numeric|gt:0',
            'grantReason' => 'nullable|string|max:500',
        ]);

        $user = User::where('email', $data['grantEmail'])->first();
        if (! $user) {
            return back()->withInput()->withErrors(['grantEmail' => 'No user found with that email.']);
        }

        try {
            $asset = Asset::findOrFail($data['grantAssetId']);
            $amount = Money::ofDecimal($data['grantAmount'], $asset->decimals, $asset->symbol);

            app(ManualGrantAction::class)->execute(
                auth('admin')->user(),
                $user,
                $asset,
                $amount,
                ($data['grantReason'] ?? null) ?: null,
            );

            return redirect()->route('admin.rewards', ['tab' => 'grants'])
                ->with('success', 'Reward granted to '.$user->email.'.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.rewards', ['tab' => 'grants'])
                ->with('error', 'Could not grant the reward: '.$e->getMessage());
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(auth('admin')->user()?->can('manage-rewards') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }
}
