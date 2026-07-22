<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Chain\SimulateInboundDepositAction;
use App\Domain\Custody\AllocateDepositAddressAction;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Admin simulation harness (DollarHub structure — controller + Blade, not
 * Livewire). Stand-in for the Blockchain Monitor & Signer; chain tick and
 * simulate-deposit are form POST actions.
 */
class SimulationController extends Controller
{
    public function index(): View
    {
        $this->authorizeSim();

        return view('admin.simulation', [
            'assets' => $this->cryptoAssets(),
            'defaultAssetId' => $this->cryptoAssets()->first()?->id,
        ]);
    }

    public function runChainTick(): RedirectResponse
    {
        $this->authorizeSim();

        Artisan::call('poisapay:chain-tick');
        $output = trim(Artisan::output());

        return back()->with('success', $output !== '' ? $output : 'Chain tick complete.');
    }

    public function simulateDeposit(
        Request $request,
        AllocateDepositAddressAction $allocate,
        SimulateInboundDepositAction $simulate,
    ): RedirectResponse {
        $this->authorizeSim();

        $data = $request->validate([
            'userEmail' => 'required|email',
            'assetId' => 'required|integer',
            'amount' => 'required|string',
        ]);

        $user = User::where('email', trim($data['userEmail']))->first();
        if (! $user) {
            return back()->withInput()->withErrors(['userEmail' => 'No user found with that email.']);
        }

        $asset = Asset::with('chain')->where('is_active', true)->where('kind', 'crypto')->find($data['assetId']);
        if (! $asset || ! $asset->chain) {
            return back()->withInput()->withErrors(['assetId' => 'Choose a crypto asset attached to a chain.']);
        }

        try {
            $money = Money::ofDecimal($data['amount'], $asset->decimals, $asset->symbol);
        } catch (\Throwable) {
            return back()->withInput()->withErrors(['amount' => 'Enter a valid amount.']);
        }

        if (! $money->isPositive()) {
            return back()->withInput()->withErrors(['amount' => 'Amount must be greater than zero.']);
        }

        try {
            $address = $allocate->execute($user, $asset->chain);
            $simulate->execute($address, $asset, $money);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Detected '.$money->format().' for '.$user->email.'. Run a chain tick to confirm & credit.');
    }

    private function authorizeSim(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasRole('super-admin') || $admin->can('view-deposits'), 403);
    }

    private function cryptoAssets()
    {
        return Asset::with('chain')
            ->where('is_active', true)
            ->where('kind', 'crypto')
            ->orderBy('sort')
            ->orderBy('symbol')
            ->get();
    }
}
