<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Public product marketing pages (linked from the footer's Products column). Each
 * product is a data entry rendered by one shared template (marketing.product).
 */
class ProductController extends Controller
{
    /** @var array<string, array<string, mixed>> */
    private const PRODUCTS = [
        'virtual-card' => [
            'eyebrow' => 'Virtual Card',
            'icon' => 'credit-card',
            'title' => 'Spend your crypto, anywhere',
            'lead' => 'Create a virtual card in seconds and pay online or in-store. Your balance converts at checkout, so you spend crypto like cash — no top-ups, no waiting.',
            'features' => [
                ['icon' => 'bolt', 'title' => 'Instant issuance', 'desc' => 'Generate a card the moment you need it — no application, no delivery wait.'],
                ['icon' => 'globe-alt', 'title' => 'Accepted everywhere', 'desc' => 'Pay at millions of merchants that accept card payments, worldwide.'],
                ['icon' => 'lock-closed', 'title' => 'Freeze & controls', 'desc' => 'Freeze, unfreeze, and set spending limits from your dashboard at any time.'],
                ['icon' => 'bell-alert', 'title' => 'Real-time alerts', 'desc' => 'Get an instant notification for every authorization and settlement.'],
            ],
        ],
        'wallet' => [
            'eyebrow' => 'Wallet',
            'icon' => 'wallet',
            'title' => 'One balance across every network',
            'lead' => 'Hold, receive and send digital assets from a single wallet. USDT on multiple chains is one pooled balance — the network only matters when you deposit or withdraw.',
            'features' => [
                ['icon' => 'squares-2x2', 'title' => 'Multi-currency', 'desc' => 'Manage crypto and fiat side by side, valued live in your base currency.'],
                ['icon' => 'arrow-down-tray', 'title' => 'Easy deposits', 'desc' => 'Get a unique address per network and fund your wallet on-chain.'],
                ['icon' => 'paper-airplane', 'title' => 'Send in seconds', 'desc' => 'Transfer to other users instantly and fee-free inside PoisaPay.'],
                ['icon' => 'chart-pie', 'title' => 'Clear overview', 'desc' => 'See your holdings, allocation and recent activity at a glance.'],
            ],
        ],
        'exchange' => [
            'eyebrow' => 'Exchange',
            'icon' => 'arrows-right-left',
            'title' => 'Swap currencies in seconds',
            'lead' => 'Convert between supported crypto and fiat currencies at transparent rates, with the spread shown up front. No order books, no surprises.',
            'features' => [
                ['icon' => 'bolt', 'title' => 'Instant swaps', 'desc' => 'Trade one asset for another in a single tap, settled to your balance.'],
                ['icon' => 'scale', 'title' => 'Transparent pricing', 'desc' => 'See the rate and spread before you confirm — what you see is what you get.'],
                ['icon' => 'clock', 'title' => 'Live rates', 'desc' => 'Prices refresh continuously so you always trade at a current rate.'],
                ['icon' => 'shield-check', 'title' => 'Settled on-ledger', 'desc' => 'Every conversion is recorded on our double-entry ledger.'],
            ],
        ],
    ];

    public function show(string $product): View
    {
        abort_unless(isset(self::PRODUCTS[$product]), 404);

        return view('marketing.product', [
            'slug' => $product,
            'product' => self::PRODUCTS[$product],
        ]);
    }
}
