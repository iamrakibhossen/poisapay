<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Card\Enums\CardProviderDriver;
use App\Http\Controllers\Controller;
use App\Models\CardProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin card-provider (BIN sponsor) catalogue (DollarHub structure — controller +
 * Blade, not Livewire). The create/edit modal POSTs to {@see save}, which handles
 * both create and update via a hidden `id`.
 */
class CardProvidersController extends Controller
{
    public function index(): View
    {
        $this->authorizeManage();

        return view('admin.card-providers', [
            'providers' => CardProvider::orderBy('sort')->orderBy('name')->get(),
            'drivers' => CardProviderDriver::configured(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $id = $request->input('id');

        $request->merge([
            'supports_virtual' => $request->boolean('supports_virtual'),
            'supports_physical' => $request->boolean('supports_physical'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => ['required', 'string', 'max:48', 'regex:/^[a-z0-9-]+$/', Rule::unique('card_providers', 'slug')->ignore($id)],
            'driver' => ['required', Rule::enum(CardProviderDriver::class)],
            'network' => 'required|in:visa,mastercard',
            'bin' => 'nullable|string|max:8',
            'settlement_currency' => 'required|string|size:3',
            'api_base' => 'nullable|string|max:160',
            'supports_virtual' => 'boolean',
            'supports_physical' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $attributes = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'driver' => $data['driver'],
            'network' => $data['network'],
            'bin' => ($data['bin'] ?? '') ?: null,
            'supports_virtual' => (bool) $data['supports_virtual'],
            'supports_physical' => (bool) $data['supports_physical'],
            'settlement_currency' => strtoupper($data['settlement_currency']),
            'api_base' => ($data['api_base'] ?? '') ?: null,
            'is_active' => (bool) $data['is_active'],
            'is_demo' => CardProviderDriver::from($data['driver'])->isSimulated(),
        ];

        // Never pass id=null to create() on a HasUuids model (mass-assignment guard).
        $id
            ? tap(CardProvider::whereKey($id)->firstOrFail())->update($attributes)
            : CardProvider::create($attributes);

        return redirect()->route('admin.card-providers')
            ->with('success', $id ? 'Provider updated.' : 'Provider added.');
    }

    public function toggleActive(Request $request, string $id): RedirectResponse
    {
        $this->authorizeManage();

        $p = CardProvider::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);

        return redirect()->route('admin.card-providers')
            ->with('success', $p->is_active ? 'Provider enabled.' : 'Provider disabled.');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth('admin')->user()?->can('manage-assets') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }
}
