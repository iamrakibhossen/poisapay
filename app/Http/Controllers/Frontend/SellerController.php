<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Creator/seller onboarding — the "Become a Seller" application (funnel platform).
 *
 * FRONTEND-FIRST: this renders the application UX for review. Submission currently
 * validates and flashes a placeholder confirmation; persistence + admin review are
 * wired in the backend phase. See docs/FUNNEL_PLATFORM_PRODUCT_DESIGN.md.
 */
class SellerController extends Controller
{
    /** Product categories a creator can pick (display order matters). */
    private const CATEGORIES = [
        'ebook' => 'eBooks & PDFs',
        'software' => 'Software & SaaS',
        'templates' => 'Templates & Themes',
        'plugins' => 'Plugins & Extensions',
        'design' => 'Design Assets & UI Kits',
        'ai' => 'AI Prompts & Tools',
        'music' => 'Music & Audio',
        'physical' => 'Physical Products',
        'membership' => 'Memberships & Communities',
        'service' => 'Services',
    ];

    /**
     * Seller home. FRONTEND-FIRST: shows the dashboard shell with empty states and
     * an onboarding CTA. Real seller state / metrics are wired in the backend phase.
     */
    public function index(Request $request): View
    {
        return view('frontend.seller.index', [
            'isSeller' => false,   // placeholder until backend: every user sees onboarding
            'stats' => [
                'revenue' => '—', 'available' => '—', 'pending' => '—', 'sales' => 0,
            ],
        ]);
    }

    /** Product types a seller can create. */
    private const PRODUCT_TYPES = [
        'digital' => ['Digital download', 'cube', 'Files delivered instantly after payment.'],
        'physical' => ['Physical product', 'truck', 'Shipped to the buyer — t-shirts, gadgets, print.'],
        'license' => ['License key', 'key', 'Software + a unique key per sale.'],
        'membership' => ['Membership', 'user-group', 'Recurring access to gated content.'],
        'subscription' => ['Subscription', 'arrow-path', 'Recurring billing for a plan.'],
        'service' => ['Service', 'briefcase', 'A delivered outcome with an intake brief.'],
    ];

    /** Seller's product list. FRONTEND-FIRST: sample rows so the UX is reviewable. */
    public function products(Request $request): View
    {
        return view('frontend.seller.products', [
            'products' => [
                ['name' => 'LaunchKit — Laravel SaaS Boilerplate', 'type' => 'Digital download', 'price' => '$49', 'status' => 'Published', 'statusColor' => 'success', 'sales' => 312, 'slug' => 'launchkit'],
                ['name' => 'Premium UI Kit', 'type' => 'Digital download', 'price' => '$19', 'status' => 'Published', 'statusColor' => 'success', 'sales' => 88, 'slug' => 'premium-ui-kit'],
                ['name' => 'PoisaHub Dev Tee', 'type' => 'Physical product', 'price' => '$25', 'status' => 'Draft', 'statusColor' => 'gray', 'sales' => 0, 'slug' => 'poisahub-tee'],
            ],
        ]);
    }

    /** Custom domains — one per sales page. FRONTEND-FIRST: connect flow + DNS + verify. */
    public function domains(Request $request): View
    {
        return view('frontend.seller.domains', [
            'target' => 'cname.poisahub.com',
            // Sales pages that don't yet have a domain (selectable when connecting one).
            'availablePages' => ['LaunchKit — Black Friday', 'PoisaHub Dev Tee'],
            'domains' => [
                ['host' => 'shop.launchkit.dev', 'page' => 'LaunchKit — Main', 'slug' => 'launchkit', 'status' => 'Verified', 'color' => 'success', 'ssl' => true],
                ['host' => 'get.premiumkit.com', 'page' => 'Premium UI Kit', 'slug' => 'premium-ui-kit', 'status' => 'Pending DNS', 'color' => 'warning', 'ssl' => false],
            ],
        ]);
    }

    public function orders(Request $request): View
    {
        return view('frontend.seller.orders', [
            'stats' => ['total' => 400, 'revenue' => '$18,240', 'pending' => 3, 'refunded' => 2],
            'orders' => [
                ['id' => 'PH-10428', 'buyer' => 'aisha@example.com', 'product' => 'LaunchKit', 'amount' => '$68', 'status' => 'Paid', 'color' => 'success', 'date' => 'Jul 24, 2026'],
                ['id' => 'PH-10427', 'buyer' => 'tanvir@example.com', 'product' => 'PoisaHub Dev Tee', 'amount' => '$30', 'status' => 'Shipped', 'color' => 'info', 'date' => 'Jul 24, 2026'],
                ['id' => 'PH-10426', 'buyer' => 'maria@example.com', 'product' => 'Premium UI Kit', 'amount' => '$19', 'status' => 'Paid', 'color' => 'success', 'date' => 'Jul 23, 2026'],
                ['id' => 'PH-10425', 'buyer' => 'karim@example.com', 'product' => 'LaunchKit', 'amount' => '$49', 'status' => 'Refunded', 'color' => 'danger', 'date' => 'Jul 22, 2026'],
                ['id' => 'PH-10424', 'buyer' => 'sadia@example.com', 'product' => 'PoisaHub Dev Tee', 'amount' => '$25', 'status' => 'Processing', 'color' => 'warning', 'date' => 'Jul 22, 2026'],
            ],
        ]);
    }

    /** Order detail + fulfillment. FRONTEND-FIRST: sample order (physical → shippable). */
    public function order(Request $request, string $id): View
    {
        return view('frontend.seller.order', [
            'order' => [
                'id' => $id,
                'status' => 'Processing', 'statusColor' => 'warning',
                'placedAt' => 'Jul 24, 2026 · 3:14 PM',
                'buyer' => ['name' => 'Aisha Karim', 'email' => 'aisha@example.com'],
                'type' => 'physical',
                'items' => [
                    ['name' => 'PoisaHub Dev Tee', 'variant' => 'Black · M', 'qty' => 1, 'price' => '$25.00'],
                ],
                'shipping' => [
                    'name' => 'Aisha Karim', 'phone' => '+8801712345678',
                    'line1' => 'House 14, Road 7, Dhanmondi', 'city' => 'Dhaka', 'postcode' => '1209', 'country' => 'Bangladesh',
                ],
                'totals' => ['subtotal' => '$25.00', 'shipping' => '$5.00', 'total' => '$30.00', 'fee' => '−$3.00', 'net' => '$27.00'],
                'timeline' => [
                    ['Paid', 'Jul 24 · 3:14 PM', true],
                    ['Processing', 'Jul 24 · 3:15 PM', true],
                    ['Shipped', 'Pending', false],
                    ['Delivered', 'Pending', false],
                ],
                'carriers' => ['Sundarban', 'Pathao', 'RedX', 'Steadfast', 'DHL', 'Other'],
                // Order-scoped conversation, shared by buyer, seller (and admin on dispute).
                'messages' => [
                    ['from' => 'buyer', 'author' => 'Aisha Karim', 'body' => 'Hi! When will my Dev Tee ship?', 'at' => 'Jul 24 · 3:20 PM'],
                    ['from' => 'seller', 'author' => 'You', 'body' => 'Preparing it now — it ships today via Pathao.', 'at' => 'Jul 24 · 3:35 PM'],
                    ['from' => 'buyer', 'author' => 'Aisha Karim', 'body' => 'Great, thank you!', 'at' => 'Jul 24 · 3:36 PM'],
                ],
            ],
        ]);
    }

    /** Seller inbox — buyer message threads. FRONTEND-FIRST: sample conversations. */
    public function inbox(Request $request): View
    {
        return view('frontend.seller.inbox', [
            'threads' => [
                [
                    'id' => 1, 'buyer' => 'Aisha Karim', 'initials' => 'AK', 'product' => 'LaunchKit',
                    'subject' => 'Question about my order', 'time' => '2h', 'unread' => true,
                    'messages' => [
                        ['from' => 'buyer', 'body' => 'Hi! Does LaunchKit include future updates?', 'at' => '10:20 AM'],
                        ['from' => 'seller', 'body' => 'Yes — lifetime updates are included with your purchase.', 'at' => '10:45 AM'],
                        ['from' => 'buyer', 'body' => 'Perfect, thank you! One more — is there Livewire support?', 'at' => '10:47 AM'],
                    ],
                ],
                [
                    'id' => 2, 'buyer' => 'Tanvir Hasan', 'initials' => 'TH', 'product' => 'PoisaHub Dev Tee',
                    'subject' => 'Shipping / delivery', 'time' => '1d', 'unread' => false,
                    'messages' => [
                        ['from' => 'buyer', 'body' => 'When will my order ship? Order PH-10427.', 'at' => 'Yesterday'],
                        ['from' => 'seller', 'body' => 'Shipped today via Pathao — tracking BD-7712-9920.', 'at' => 'Yesterday'],
                    ],
                ],
                [
                    'id' => 3, 'buyer' => 'Maria Lopez', 'initials' => 'ML', 'product' => 'Premium UI Kit',
                    'subject' => 'Problem with the product', 'time' => '3d', 'unread' => false,
                    'messages' => [
                        ['from' => 'buyer', 'body' => 'The Figma file link seems broken.', 'at' => 'Jul 22'],
                        ['from' => 'seller', 'body' => 'Sorry about that — fixed and re-sent the link.', 'at' => 'Jul 22'],
                        ['from' => 'buyer', 'body' => 'Got it, works now. Thanks!', 'at' => 'Jul 22'],
                    ],
                ],
            ],
        ]);
    }

    public function reviews(Request $request): View
    {
        return view('frontend.seller.reviews', [
            'summary' => ['avg' => '4.9', 'count' => 214],
            'reviews' => [
                ['buyer' => 'Aisha K.', 'product' => 'LaunchKit', 'rating' => 5, 'body' => 'I launched my MVP in 4 days. Paid for itself instantly.', 'date' => '2 days ago', 'reply' => null],
                ['buyer' => 'Tanvir H.', 'product' => 'Premium UI Kit', 'rating' => 5, 'body' => 'Clean components, saved me hours.', 'date' => '4 days ago', 'reply' => 'Thanks Tanvir — glad it helped!'],
                ['buyer' => 'Maria L.', 'product' => 'LaunchKit', 'rating' => 4, 'body' => 'Great starter. Docs could be a bit deeper.', 'date' => '1 week ago', 'reply' => null],
            ],
        ]);
    }

    public function customers(Request $request): View
    {
        return view('frontend.seller.customers', [
            'customers' => [
                ['name' => 'Aisha Karim', 'email' => 'aisha@example.com', 'orders' => 4, 'spent' => '$246', 'since' => 'Jan 2026'],
                ['name' => 'Tanvir Hasan', 'email' => 'tanvir@example.com', 'orders' => 2, 'spent' => '$88', 'since' => 'Mar 2026'],
                ['name' => 'Maria Lopez', 'email' => 'maria@example.com', 'orders' => 1, 'spent' => '$49', 'since' => 'Jul 2026'],
                ['name' => 'Karim Ahmed', 'email' => 'karim@example.com', 'orders' => 3, 'spent' => '$154', 'since' => 'Feb 2026'],
            ],
        ]);
    }

    public function coupons(Request $request): View
    {
        return view('frontend.seller.coupons', [
            'coupons' => [
                ['code' => 'LAUNCH20', 'type' => '20% off', 'used' => 142, 'limit' => 500, 'status' => 'Active', 'color' => 'success', 'expires' => 'Aug 31, 2026'],
                ['code' => 'BLACKFRIDAY', 'type' => '$15 off', 'used' => 0, 'limit' => 1000, 'status' => 'Scheduled', 'color' => 'warning', 'expires' => 'Nov 29, 2026'],
                ['code' => 'EARLYBIRD', 'type' => '30% off', 'used' => 200, 'limit' => 200, 'status' => 'Expired', 'color' => 'gray', 'expires' => 'Jun 1, 2026'],
            ],
        ]);
    }

    public function analytics(Request $request): View
    {
        return view('frontend.seller.analytics', [
            'kpis' => [
                ['Visitors · 30d', '12,480', 'eye', 'text-neutral-900', '+18%'],
                ['Conversion', '3.2%', 'cursor-arrow-rays', 'text-emerald-600', '+0.4pt'],
                ['Revenue · 30d', '$6,120', 'banknotes', 'text-neutral-900', '+24%'],
                ['Avg. order value', '$71.40', 'shopping-cart', 'text-neutral-900', '+$6'],
            ],
            'funnel' => [
                ['Page views', 12480, 100],
                ['Checkout started', 1090, 9],
                ['Purchased', 399, 3],
                ['Upsell accepted', 88, 1],
            ],
            'sources' => [
                ['Facebook Ads', 46], ['Direct / link', 24], ['Google', 16], ['YouTube', 9], ['Other', 5],
            ],
        ]);
    }

    public function earnings(Request $request): View
    {
        return view('frontend.seller.earnings', [
            'balances' => ['available' => '$1,842.50', 'pending' => '$430.00', 'withdrawn' => '$14,120.00', 'revenue' => '$16,392.50'],
            'payouts' => [
                ['amount' => '$1,200.00', 'method' => 'Bank transfer', 'status' => 'Paid', 'color' => 'success', 'date' => 'Jul 15, 2026'],
                ['amount' => '$900.00', 'method' => 'USDT wallet', 'status' => 'Paid', 'color' => 'success', 'date' => 'Jun 30, 2026'],
                ['amount' => '$430.00', 'method' => 'Bank transfer', 'status' => 'Processing', 'color' => 'warning', 'date' => 'Jul 25, 2026'],
            ],
        ]);
    }

    /**
     * Sales pages. A seller can create many — a product can even have several
     * pages (e.g. different ad campaigns). Create one against a product, then
     * customize it in the builder.
     */
    public function salesPages(Request $request): View
    {
        return view('frontend.seller.sales-pages-index', [
            'products' => ['LaunchKit — Laravel SaaS Boilerplate', 'Premium UI Kit', 'PoisaHub Dev Tee'],
            'pages' => [
                ['name' => 'LaunchKit — Main', 'product' => 'LaunchKit', 'slug' => 'launchkit', 'status' => 'Published', 'color' => 'success', 'domain' => 'shop.launchkit.dev', 'views' => '8.2k', 'conv' => '3.4%'],
                ['name' => 'LaunchKit — Black Friday', 'product' => 'LaunchKit', 'slug' => 'launchkit-bf', 'status' => 'Draft', 'color' => 'gray', 'domain' => null, 'views' => '—', 'conv' => '—'],
                ['name' => 'Premium UI Kit', 'product' => 'Premium UI Kit', 'slug' => 'premium-ui-kit', 'status' => 'Published', 'color' => 'success', 'domain' => null, 'views' => '2.1k', 'conv' => '5.1%'],
                ['name' => 'PoisaHub Dev Tee', 'product' => 'PoisaHub Dev Tee', 'slug' => 'poisahub-tee', 'status' => 'Draft', 'color' => 'gray', 'domain' => null, 'views' => '—', 'conv' => '—'],
            ],
        ]);
    }

    /** Sales-page builder for a specific product. FRONTEND-FIRST: live preview + editing. */
    public function editSalesPage(Request $request, string $slug): View
    {
        $names = [
            'launchkit' => 'LaunchKit — Laravel SaaS Boilerplate',
            'premium-ui-kit' => 'Premium UI Kit',
            'poisahub-tee' => 'PoisaHub Dev Tee',
        ];

        return view('frontend.seller.sales-pages', [
            'slug' => $slug,
            'product' => $names[$slug] ?? 'Product',
            'themes' => ['#2563eb' => 'Blue', '#7c3aed' => 'Violet', '#059669' => 'Emerald', '#e11d48' => 'Rose', '#ea580c' => 'Orange', '#0f172a' => 'Slate'],
        ]);
    }

    /** Funnel builder. FRONTEND-FIRST: a sample funnel so the flow is reviewable. */
    public function funnels(Request $request): View
    {
        return view('frontend.seller.funnels', [
            'funnel' => [
                'name' => 'LaunchKit funnel',
                'product' => 'LaunchKit — Laravel SaaS Boilerplate',
                'price' => '$49',
                'steps' => [
                    ['kind' => 'bump', 'label' => 'Order bump', 'offer' => 'Premium UI Kit add-on', 'price' => '$19', 'rate' => 34, 'icon' => 'plus-circle', 'where' => 'Shown inside checkout'],
                    ['kind' => 'upsell', 'label' => 'Upsell #1', 'offer' => 'Extended Team License', 'price' => '$59', 'rate' => 22, 'icon' => 'arrow-trending-up', 'where' => 'One-click after payment'],
                    ['kind' => 'downsell', 'label' => 'Downsell', 'offer' => 'Single-site License', 'price' => '$29', 'rate' => 11, 'icon' => 'arrow-trending-down', 'where' => 'If the upsell is skipped'],
                ],
            ],
        ]);
    }

    public function createProduct(Request $request): View
    {
        return view('frontend.seller.product-create', [
            'types' => self::PRODUCT_TYPES,
            'assets' => Asset::where('is_active', true)->get()->groupBy('currency_id')
                ->map(fn ($g) => $g->sortBy('id')->first())->sortBy('symbol')
                ->map(fn ($a) => ['id' => $a->id, 'symbol' => $a->symbol])->values(),
        ]);
    }

    /** FRONTEND-FIRST stub: validates and flashes a confirmation (no persistence). */
    public function storeProduct(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
        ]);

        return redirect()->route('seller.products')
            ->with('success', 'Product created. (Preview: not yet persisted — a sales page would be generated automatically.)');
    }

    public function apply(Request $request): View
    {
        return view('frontend.seller.apply', [
            'categories' => self::CATEGORIES,
            'countries' => $this->countries(),
            'settlementAssets' => Asset::where('is_active', true)
                ->get()->groupBy('currency_id')
                ->map(fn ($g) => $g->sortBy('id')->first())
                ->sortBy('symbol')
                ->map(fn ($a) => ['id' => $a->id, 'symbol' => $a->symbol, 'name' => (string) $a->name])
                ->values(),
            'defaultCountry' => (string) ($request->user()->country ?? 'BD'),
        ]);
    }

    /** FRONTEND-FIRST stub: validates the form and flashes a confirmation. */
    public function submitApplication(Request $request): RedirectResponse
    {
        $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:160'],
            'country' => ['required', 'string', 'size:2'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string', 'max:32'],
            'settlement_asset_id' => ['nullable', 'integer'],
            'terms' => ['accepted'],
        ]);

        return redirect()->route('seller.apply')
            ->with('success', 'Application submitted — our team will review it shortly. (Preview: not yet persisted.)');
    }

    /** @return array<string, string> ISO2 => name (short, common-first list). */
    private function countries(): array
    {
        return [
            'BD' => 'Bangladesh', 'IN' => 'India', 'PK' => 'Pakistan', 'US' => 'United States',
            'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia', 'MY' => 'Malaysia', 'SG' => 'Singapore', 'ID' => 'Indonesia',
            'NG' => 'Nigeria', 'DE' => 'Germany', 'FR' => 'France', 'NL' => 'Netherlands', 'OT' => 'Other',
        ];
    }
}
