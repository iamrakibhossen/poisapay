<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Shop\Actions\Domain\ConnectDomain;
use App\Shop\Actions\Domain\RemoveDomain;
use App\Shop\Actions\Domain\ReverifyDomain;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Domain;
use App\Shop\Models\SalesPage;
use App\Shop\Services\Domain\DnsInstructionBuilder;
use App\Shop\Services\SellerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Merchant custom-domain management (server-rendered Blade, form POST → redirect
 * + flash). Each sales page can have one domain; the merchant connects it, adds
 * the shown DNS records, and we verify + provision SSL in the background.
 */
class DomainController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, SellerService $sellers, DnsInstructionBuilder $dns): View
    {
        $seller = $sellers->forUser($request->user());

        $domains = $seller
            ? $seller->domains()->with('salesPage:id,name,slug')->latest()->get()
                ->map(fn (Domain $d) => [
                    'model' => $d,
                    'instructions' => $dns->for($d),
                ])->all()
            : [];

        // Published/draft pages that don't yet have a domain — connectable targets.
        $availablePages = $seller
            ? $seller->salesPages()
                ->whereIn('status', [SalesPageStatus::Draft->value, SalesPageStatus::Published->value])
                ->whereDoesntHave('domain')
                ->get(['id', 'name', 'slug'])
            : collect();

        return view('frontend.seller.domains', [
            'domains' => $domains,
            'availablePages' => $availablePages,
            'cnameTarget' => (string) config('shop.custom_domains.cname_target'),
        ]);
    }

    public function store(Request $request, SellerService $sellers, ConnectDomain $connect): RedirectResponse
    {
        $data = $request->validate([
            'sales_page_id' => ['required', 'string'],
            'host' => ['required', 'string', 'max:255'],
        ]);

        $seller = $sellers->forUser($request->user());
        if ($seller === null) {
            return back()->with('error', __('You need a shop before connecting a domain.'));
        }

        $page = SalesPage::where('seller_id', $seller->getKey())->find($data['sales_page_id']);
        if ($page === null) {
            return back()->with('error', __('That sales page was not found.'));
        }

        try {
            $connect->execute($seller, $page, $data['host']);
        } catch (ShopException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Domain connected. Add the DNS records shown, then verify.'));
    }

    public function verify(Request $request, Domain $domain, ReverifyDomain $reverify): RedirectResponse
    {
        $this->authorize('update', $domain);
        $reverify->execute($domain);

        return back()->with('success', __('Re-checking your DNS records now. This can take a moment.'));
    }

    public function destroy(Request $request, Domain $domain, RemoveDomain $remove): RedirectResponse
    {
        $this->authorize('delete', $domain);
        $remove->execute($domain);

        return back()->with('success', __('Domain removed.'));
    }
}
