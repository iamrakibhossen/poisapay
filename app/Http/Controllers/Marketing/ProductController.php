<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Public product marketing pages (linked from the nav + footer). Each product is a
 * data entry rendered by one shared template (marketing.product).
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
            'stats' => [
                ['value' => 'Instant', 'label' => 'Card issuance'],
                ['value' => 'Worldwide', 'label' => 'Acceptance'],
                ['value' => '৳0', 'label' => 'Monthly fee'],
                ['value' => '24/7', 'label' => 'Card controls'],
            ],
            'features' => [
                ['icon' => 'bolt', 'title' => 'Instant issuance', 'desc' => 'Generate a card the moment you need it — no application, no delivery wait.'],
                ['icon' => 'globe-alt', 'title' => 'Accepted everywhere', 'desc' => 'Pay at millions of merchants that accept card payments, worldwide.'],
                ['icon' => 'lock-closed', 'title' => 'Freeze & controls', 'desc' => 'Freeze, unfreeze, and set spending limits from your dashboard at any time.'],
                ['icon' => 'bell-alert', 'title' => 'Real-time alerts', 'desc' => 'Get an instant notification for every authorization and settlement.'],
                ['icon' => 'device-phone-mobile', 'title' => 'Add to mobile wallet', 'desc' => 'Save the card to your phone and tap to pay in-store.'],
                ['icon' => 'shield-check', 'title' => 'Built-in protection', 'desc' => 'Every transaction is risk-scored and monitored for fraud in real time.'],
            ],
            'steps' => [
                ['title' => 'Create your card', 'desc' => 'Open your dashboard and issue a virtual card in a single tap.'],
                ['title' => 'Fund with crypto', 'desc' => 'Keep any supported asset in your wallet — it converts automatically at checkout.'],
                ['title' => 'Pay anywhere', 'desc' => 'Use the card online, or add it to your mobile wallet and tap to pay.'],
            ],
            'faqs' => [
                ['q' => 'Is it a virtual or physical card?', 'a' => 'It\'s a virtual card you can use online immediately, and add to your mobile wallet to tap to pay in-store.'],
                ['q' => 'Which balance does it spend from?', 'a' => 'Your card draws from your PoisaPay balance and converts your chosen asset to the merchant\'s currency at the point of sale.'],
                ['q' => 'Can I freeze or cancel my card?', 'a' => 'Yes. You can freeze, unfreeze, or close a card instantly from your dashboard, and set per-transaction and daily limits.'],
                ['q' => 'Are there any fees?', 'a' => 'There are no setup or monthly fees. Any applicable transaction or conversion costs are shown before you spend.'],
            ],
        ],
        'wallet' => [
            'eyebrow' => 'Wallet',
            'icon' => 'wallet',
            'title' => 'One balance across every network',
            'lead' => 'Hold, receive and send digital assets from a single wallet. USDT on multiple chains is one pooled balance — the network only matters when you deposit or withdraw.',
            'stats' => [
                ['value' => 'Multi-chain', 'label' => 'One balance'],
                ['value' => 'Instant', 'label' => 'Internal transfers'],
                ['value' => '৳0', 'label' => 'Send fees'],
                ['value' => 'Live', 'label' => 'Valuation'],
            ],
            'features' => [
                ['icon' => 'squares-2x2', 'title' => 'Multi-currency', 'desc' => 'Manage crypto and fiat side by side, valued live in your base currency.'],
                ['icon' => 'arrow-down-tray', 'title' => 'Easy deposits', 'desc' => 'Get a unique address per network and fund your wallet on-chain.'],
                ['icon' => 'paper-airplane', 'title' => 'Send in seconds', 'desc' => 'Transfer to other users instantly and fee-free inside PoisaPay.'],
                ['icon' => 'chart-pie', 'title' => 'Clear overview', 'desc' => 'See your holdings, allocation and recent activity at a glance.'],
                ['icon' => 'link', 'title' => 'Pooled per coin', 'desc' => 'One coin is one balance — no juggling the same asset across chains.'],
                ['icon' => 'lock-closed', 'title' => 'Custodial security', 'desc' => 'Assets are safeguarded with cold storage and continuous reconciliation.'],
            ],
            'steps' => [
                ['title' => 'Add funds', 'desc' => 'Deposit crypto to your unique address, or top up your Taka balance.'],
                ['title' => 'Manage in one place', 'desc' => 'Track every asset, its value and history from a single dashboard.'],
                ['title' => 'Send or spend', 'desc' => 'Transfer to friends instantly, swap, or spend with your card.'],
            ],
            'faqs' => [
                ['q' => 'What does "one balance per coin" mean?', 'a' => 'If you hold USDT on several networks, PoisaPay shows and spends it as a single pooled balance. The network only matters when you deposit or withdraw on-chain.'],
                ['q' => 'Are internal transfers really free?', 'a' => 'Yes — sending to another PoisaPay user is instant and fee-free, because it settles on our internal ledger, not on-chain.'],
                ['q' => 'How are my assets kept safe?', 'a' => 'Balances are held custodially, with the majority of crypto in offline cold storage and continuous reconciliation against on-chain holdings.'],
            ],
        ],
        'exchange' => [
            'eyebrow' => 'Exchange',
            'icon' => 'arrows-right-left',
            'title' => 'Swap currencies in seconds',
            'lead' => 'Convert between supported crypto and fiat currencies at transparent rates, with the spread shown up front. No order books, no surprises.',
            'stats' => [
                ['value' => 'Instant', 'label' => 'Swaps'],
                ['value' => 'Upfront', 'label' => 'Pricing'],
                ['value' => 'Live', 'label' => 'Rates'],
                ['value' => '5+', 'label' => 'Currencies'],
            ],
            'features' => [
                ['icon' => 'bolt', 'title' => 'Instant swaps', 'desc' => 'Trade one asset for another in a single tap, settled to your balance.'],
                ['icon' => 'scale', 'title' => 'Transparent pricing', 'desc' => 'See the rate and spread before you confirm — what you see is what you get.'],
                ['icon' => 'clock', 'title' => 'Live rates', 'desc' => 'Prices refresh continuously so you always trade at a current rate.'],
                ['icon' => 'shield-check', 'title' => 'Settled on-ledger', 'desc' => 'Every conversion is recorded on our double-entry ledger.'],
                ['icon' => 'star', 'title' => 'Save your pairs', 'desc' => 'Pin the pairs you trade most for one-tap access.'],
                ['icon' => 'arrows-right-left', 'title' => 'Crypto ↔ Taka', 'desc' => 'Move seamlessly between digital assets and your local currency.'],
            ],
            'steps' => [
                ['title' => 'Choose your pair', 'desc' => 'Pick what you want to swap from and to.'],
                ['title' => 'Review the rate', 'desc' => 'See the exact amount you\'ll receive, spread included, before confirming.'],
                ['title' => 'Confirm', 'desc' => 'The swap settles to your balance instantly.'],
            ],
            'faqs' => [
                ['q' => 'How is the exchange rate set?', 'a' => 'Rates come from live market data with a small, transparent spread that is always shown before you confirm the swap.'],
                ['q' => 'How fast are swaps?', 'a' => 'Swaps are instant — they settle to your PoisaPay balance the moment you confirm, on our internal ledger.'],
                ['q' => 'Can I swap between crypto and Taka?', 'a' => 'Yes. You can convert between supported crypto assets and Taka in either direction.'],
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
