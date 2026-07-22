<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AssetKind;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin catalogue (controller + Blade, not Livewire). Organised as
 * "one coin, many networks": a {@see Currency} owns coin-level identity and
 * groups its per-chain {@see Asset} deployments. Coins and networks each have
 * their own create/edit modal (Alpine dialog → traditional POST). The per-chain
 * denormalised columns (symbol/name/kind…) are kept in sync from the currency so
 * the ledger and every existing `$asset->symbol` reader keep working unchanged.
 */
class AssetsController extends Controller
{
    public function index(): View
    {
        $this->authorizeManage();

        return view('admin.assets', [
            'currencies' => Currency::with(['assets.chain'])->orderBy('sort')->orderBy('symbol')->get(),
            'chains' => Chain::orderBy('name')->get(),
            'chainOptions' => Chain::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Create or update a coin (logical currency) + propagate identity to its networks. */
    public function saveCurrency(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $id = $request->input('id');

        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:16', Rule::unique('currencies', 'symbol')->ignore($id)],
            'name' => ['required', 'string', 'max:48'],
            'kind' => ['required', 'in:crypto,fiat'],
            'is_stablecoin' => ['boolean'],
            'sort' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_stablecoin'] = $request->boolean('is_stablecoin');
        $data['is_active'] = $request->boolean('is_active');

        $currency = $id ? Currency::findOrFail($id) : new Currency;
        $currency->fill($data)->save();

        // Keep the per-network denormalised identity columns aligned with the coin.
        $currency->assets()->update([
            'symbol' => $currency->symbol,
            'name' => $currency->name,
            'kind' => $currency->kind->value,
            'is_stablecoin' => $currency->is_stablecoin,
            'currency_code' => $this->assetCurrencyCode($currency),
        ]);

        return redirect()->route('admin.assets')->with('success', $id ? 'Coin updated.' : 'Coin created.');
    }

    public function toggleCurrency(string $id): RedirectResponse
    {
        $this->authorizeManage();

        $currency = Currency::findOrFail($id);
        $currency->update(['is_active' => ! $currency->is_active]);
        // Cascade the flag to its networks so nothing dangles active under an inactive coin.
        $currency->assets()->update(['is_active' => $currency->is_active]);

        return redirect()->route('admin.assets')->with('success', $currency->is_active
            ? "{$currency->symbol} activated."
            : "{$currency->symbol} deactivated.");
    }

    /** Create or update one network (per-chain deployment) of a coin. */
    public function saveNetwork(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'chain_id' => ['nullable', 'integer', 'exists:chains,id'],
            'contract_address' => ['nullable', 'string', 'max:128'],
            'decimals' => ['required', 'integer', 'min:0', 'max:36'],
            'withdrawal_min' => ['required', 'string'],
            'withdrawal_fee' => ['required', 'string'],
            'is_active' => ['boolean'],
            'sort' => ['required', 'integer', 'min:0'],
        ]);

        $currency = Currency::findOrFail($data['currency_id']);

        $chainId = ($data['chain_id'] ?? null) !== null ? (int) $data['chain_id'] : null;
        $contract = ($data['contract_address'] ?? '') === '' ? null : $data['contract_address'];
        $id = $request->input('id');

        $this->assertChainSlotFree($chainId, $contract, $id);

        // Identity columns are inherited from the coin (single source of truth).
        $payload = [
            'currency_id' => $currency->id,
            'symbol' => $currency->symbol,
            'name' => $currency->name,
            'kind' => $currency->kind->value,
            'currency_code' => $this->assetCurrencyCode($currency),
            'is_stablecoin' => $currency->is_stablecoin,
            'chain_id' => $chainId,
            'contract_address' => $contract,
            'decimals' => $data['decimals'],
            'withdrawal_min' => $data['withdrawal_min'],
            'withdrawal_fee' => $data['withdrawal_fee'],
            'is_active' => $request->boolean('is_active'),
            'sort' => $data['sort'],
        ];

        try {
            if ($id) {
                Asset::findOrFail($id)->update($payload);
                $message = 'Network updated.';
            } else {
                Asset::create($payload);
                $message = 'Network added.';
            }
        } catch (QueryException $e) {
            // Race backstop for the DB uniqueness indexes.
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'duplicate key')) {
                $isNative = str_contains($e->getMessage(), 'uq_native_per_chain');

                throw ValidationException::withMessages($isNative
                    ? ['chain_id' => 'This chain already has a native coin. A chain can only have one native asset (a coin with no contract address).']
                    : ['contract_address' => 'A network with this contract address already exists on the selected chain.']);
            }

            throw $e;
        }

        return redirect()->route('admin.assets')->with('success', $message);
    }

    public function toggleActive(Request $request, string $id): RedirectResponse
    {
        $this->authorizeManage();

        $asset = Asset::with('chain')->findOrFail($id);
        $asset->update(['is_active' => ! $asset->is_active]);

        $network = $asset->chain?->name ?? 'network';

        return redirect()->route('admin.assets')->with('success', $asset->is_active
            ? "{$asset->symbol} on {$network} activated."
            : "{$asset->symbol} on {$network} deactivated.");
    }

    /**
     * Reject a coin+chain slot that is already occupied: one native asset (no
     * contract) per chain, and one asset per (chain, contract_address) pair.
     */
    private function assertChainSlotFree(?int $chainId, ?string $contract, int|string|null $ignoreId): void
    {
        if ($chainId === null) {
            return; // fiat / chain-less assets are unconstrained here
        }

        $query = Asset::where('chain_id', $chainId)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        if ($contract === null) {
            if ((clone $query)->whereNull('contract_address')->exists()) {
                throw ValidationException::withMessages([
                    'chain_id' => 'This chain already has a native coin. A chain can only have one native asset (a coin with no contract address).',
                ]);
            }

            return;
        }

        if ((clone $query)->where('contract_address', $contract)->exists()) {
            throw ValidationException::withMessages([
                'contract_address' => 'A network with this contract address already exists on the selected chain.',
            ]);
        }
    }

    /** Fiat coins carry a 3-letter code (== symbol); crypto coins have none. */
    private function assetCurrencyCode(Currency $currency): ?string
    {
        return $currency->kind === AssetKind::Fiat ? $currency->symbol : null;
    }

    private function authorizeManage(): void
    {
        abort_unless(auth('admin')->user()?->can('manage-assets') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }
}
