<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public product sales page — /p/{slug}. Standalone, no app nav, conversion-first.
 *
 * FRONTEND-FIRST: renders from a sample product so the design is reviewable.
 * Real products, checkout, and delivery are wired in the backend phase.
 */
class PublicSalesController extends Controller
{
    public function show(string $slug): View
    {
        return view('funnel.sales', ['product' => $this->sampleProduct($slug)]);
    }

    /** Checkout hands off to the PoisaPay-hosted payment page (like a gateway redirect). */
    public function checkout(Request $request, string $slug): RedirectResponse
    {
        return redirect()->route('funnel.pay', [
            'slug' => $slug,
            'bump' => $request->boolean('bump') ? 1 : null,
        ]);
    }

    /** PoisaPay-hosted payment page — the buyer confirms with their PoisaPay wallet. */
    public function pay(Request $request, string $slug): View
    {
        $product = $this->sampleProduct($slug);
        $bump = $request->boolean('bump');
        $total = 49 + ($bump ? 19 : 0);

        return view('funnel.pay', [
            'product' => $product,
            'bump' => $bump,
            'total' => $total,
            // Buyer's PoisaPay wallet (frontend-first sample).
            'wallet' => ['balance' => 128.40, 'currency' => 'USD'],
        ]);
    }

    /** FRONTEND-FIRST stub: confirms payment and lands on thank-you. */
    public function payConfirm(Request $request, string $slug): RedirectResponse
    {
        return redirect()->route('funnel.thankyou', ['slug' => $slug]);
    }

    public function thankYou(string $slug): View
    {
        return view('funnel.thank-you', ['product' => $this->sampleProduct($slug)]);
    }

    /** @return array<string, mixed> */
    private function sampleProduct(string $slug): array
    {
        return [
            'slug' => $slug,
            'name' => 'LaunchKit — Laravel SaaS Boilerplate',
            'tagline' => 'Ship your SaaS in a weekend, not a quarter.',
            'summary' => 'A production-ready Laravel starter with auth, billing, teams, and a beautiful admin — so you build features, not plumbing.',
            'price' => '$49',
            'comparePrice' => '$99',
            'seller' => ['name' => 'Rahim Studios', 'initials' => 'RS'],
            'rating' => '4.9',
            'reviewsCount' => 214,
            'salesCount' => '3,100+',
            'cover' => null,
            'bump' => ['name' => 'Premium UI Kit add-on', 'price' => '$19', 'desc' => '30 extra Blade + Tailwind components.'],
            'features' => [
                ['bolt', 'Auth & teams', 'Registration, 2FA, roles, invitations — done.'],
                ['credit-card', 'Billing built in', 'Subscriptions, plans and invoices out of the box.'],
                ['sparkles', 'Beautiful UI', 'Tailwind + Alpine components and a polished admin.'],
                ['wrench-screwdriver', 'DX first', 'Tests, CI, and clean architecture you can extend.'],
            ],
            'benefits' => [
                'Save 100+ hours of boilerplate setup',
                'Clean, documented, and fully tested codebase',
                'Lifetime updates and changelog',
                '6 months of priority support',
            ],
            'testimonials' => [
                ['Aisha K.', 'Indie founder', 'I launched my MVP in 4 days. This paid for itself instantly.'],
                ['Tanvir H.', 'Agency lead', 'We use LaunchKit for every client project now. Huge time saver.'],
                ['Maria L.', 'Developer', 'The cleanest Laravel starter I have used. Worth every taka.'],
            ],
            'faq' => [
                ['What do I get?', 'Full source code, docs, and lifetime updates delivered instantly after purchase.'],
                ['Which license?', 'A single-developer license; extended and team licenses available as an upsell.'],
                ['Refund policy?', '14-day money-back guarantee, no questions asked.'],
                ['Do I need PoisaHub?', 'No — pay by wallet, card, crypto, bank, or mobile money at checkout.'],
            ],
        ];
    }
}
