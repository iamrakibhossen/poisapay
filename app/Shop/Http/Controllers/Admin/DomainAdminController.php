<?php

declare(strict_types=1);

namespace App\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Shop\Actions\Domain\ReverifyDomain;
use App\Shop\Actions\Domain\SetDomainDisabled;
use App\Shop\Enums\DomainSslStatus;
use App\Shop\Enums\DomainStatus;
use App\Shop\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operator custom-domains console (DollarHub structure — controller + Blade).
 * Search across every merchant's domains, inspect owner + verification/SSL state,
 * and disable or re-verify a domain. Read-mostly; the two mutations are audited.
 */
class DomainAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView();

        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        $domains = Domain::query()
            ->with(['seller.user', 'salesPage:id,name,slug'])
            ->when($status === 'disabled', fn ($q) => $q->whereNotNull('disabled_at'))
            ->when(in_array($status, array_column(DomainStatus::cases(), 'value'), true),
                fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('host', 'like', '%'.$search.'%')
                ->orWhereHas('seller.user', fn ($u) => $u
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'))))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.shop.domains.index', [
            'domains' => $domains,
            'search' => $search,
            'status' => $status,
            'stats' => [
                'total' => Domain::count(),
                'verified' => Domain::where('status', DomainStatus::Verified->value)->count(),
                'pending' => Domain::whereIn('status', [DomainStatus::Pending->value, DomainStatus::Verifying->value])->count(),
                'failed' => Domain::where('status', DomainStatus::Failed->value)->count(),
                'ssl_active' => Domain::where('ssl_status', DomainSslStatus::Active->value)->count(),
                'disabled' => Domain::whereNotNull('disabled_at')->count(),
            ],
        ]);
    }

    public function disable(Request $request, Domain $domain, SetDomainDisabled $action): RedirectResponse
    {
        $this->authorizeManage();
        $action->execute($domain, true);

        return back()->with('success', __('Domain disabled.'));
    }

    public function enable(Request $request, Domain $domain, SetDomainDisabled $action): RedirectResponse
    {
        $this->authorizeManage();
        $action->execute($domain, false);

        return back()->with('success', __('Domain re-enabled; re-verifying.'));
    }

    public function reverify(Request $request, Domain $domain, ReverifyDomain $action): RedirectResponse
    {
        $this->authorizeManage();
        $action->execute($domain);

        return back()->with('success', __('Re-verification queued.'));
    }

    private function authorizeView(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin && ($admin->can('view-sellers') || $admin->hasRole('super-admin')), 403);
    }

    private function authorizeManage(): void
    {
        $admin = auth('admin')->user();
        abort_unless($admin && ($admin->can('manage-sellers') || $admin->hasRole('super-admin')), 403);
    }
}
