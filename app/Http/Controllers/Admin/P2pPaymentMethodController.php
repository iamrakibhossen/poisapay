<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\P2pPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin catalog of P2P payment rails (bKash, bank, Wise, …). Each method carries
 * a `fields` schema — the inputs a user fills when saving a payout account — so
 * "bank" can require more detail (bank name, branch, routing) than a mobile rail.
 * Controller + Blade (DollarHub structure), not Livewire.
 */
class P2pPaymentMethodController extends Controller
{
    public function index(): View
    {
        $this->authorizeManage();

        return view('admin.p2p-payment-methods', [
            'methods' => P2pPaymentMethod::withCount('userAccounts')->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function show(P2pPaymentMethod $method): View
    {
        $this->authorizeManage();

        return view('admin.p2p-payment-method-show', [
            'method' => $method->loadCount('userAccounts'),
            // Account details are encrypted PII — the list stays privacy-preserving
            // (who + their label + status), never the decrypted account contents.
            'accounts' => $method->userAccounts()->with('user:id,name,email')->latest()->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $this->validated($request);
        P2pPaymentMethod::create($data);

        return back()->with('success', 'Payment method created.');
    }

    public function update(Request $request, P2pPaymentMethod $method): RedirectResponse
    {
        $this->authorizeManage();

        $method->update($this->validated($request, $method));

        return back()->with('success', 'Payment method updated.');
    }

    public function destroy(Request $request, P2pPaymentMethod $method): RedirectResponse
    {
        $this->authorizeManage();

        // Reference data: refuse to hard-delete a method still referenced by user
        // accounts, ads, or orders (which would trigger an FK violation). Disable it.
        $inUse = $method->userAccounts()->exists()
            || DB::table('p2p_ad_payment_methods')->where('payment_method_id', $method->id)->exists()
            || DB::table('p2p_orders')->where('payment_method_id', $method->id)->exists();

        if ($inUse) {
            return back()->with('error', 'This payment method is in use by ads, orders, or user accounts — disable it instead of deleting.');
        }

        $method->delete();

        // Land on the list — the detail page for this record no longer exists.
        return redirect()->route('admin.p2p-payment-methods')->with('success', 'Payment method removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?P2pPaymentMethod $method = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('p2p_payment_methods', 'key')->ignore($method?->id)],
            'type' => ['required', 'in:mobile,bank,wallet,other'],
            'country' => ['nullable', 'string', 'size:2'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'fields' => ['array'],
            'fields.*.key' => ['nullable', 'string', 'max:40'],
            'fields.*.label' => ['nullable', 'string', 'max:60'],
        ]);

        // Normalise the repeatable field rows into a clean schema array.
        $fields = [];
        foreach ($request->input('fields', []) as $row) {
            $key = Str::slug((string) ($row['key'] ?? ''), '_');
            $label = trim((string) ($row['label'] ?? ''));
            if ($key === '' || $label === '') {
                continue;
            }
            $fields[] = ['key' => $key, 'label' => $label, 'required' => ! empty($row['required'])];
        }

        return [
            'name' => $data['name'],
            'key' => $data['key'],
            'type' => $data['type'],
            'country' => $data['country'] ? strtoupper($data['country']) : null,
            'sort' => $data['sort'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'fields' => $fields,
        ];
    }

    private function authorizeManage(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin && ($admin->can('manage-p2p') || $admin->hasRole('super-admin')), 403);
    }
}
