<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Landing module configuration
|--------------------------------------------------------------------------
|
| Dedicated settings for the isolated Landing module. Nothing here is read by
| any other module. `products` drives the /products/{slug} marketing pages;
| `nav_products` / `footer` centralise the public link maps so the isolated
| navbar and footer stay DRY and keep pointing at stable route names.
|
*/

return [

    // Primary navigation products (slug => label), rendered in the landing navbar.
    'nav_products' => [
        'shop' => 'Shop',
        'virtual-card' => 'Virtual Card',
        'wallet' => 'Wallet',
        'exchange' => 'Exchange',
        'merchant-pay' => 'Merchant Pay',
    ],

    // Footer link columns. Each entry is [label, route-or-url, optional-arg].
    'footer' => [
        'products' => [
            ['Virtual Card', 'products.show', 'virtual-card'],
            ['Wallet', 'products.show', 'wallet'],
            ['Exchange', 'products.show', 'exchange'],
            ['Merchant Pay', 'products.show', 'merchant-pay'],
        ],
        'company' => [
            ['About us', 'page.show', 'about-us'],
            ['Careers', 'page.show', 'careers'],
            ['Blog', 'page.show', 'blog'],
            ['Contact', 'page.show', 'contact'],
        ],
        'resources' => [
            ['Help Center', 'help-center', null],
            ['Live Prices', 'marketing.prices', null],
            ['System Status', 'status', null],
            ['API', 'page.show', 'api'],
        ],
    ],

    /*
     * Product marketing pages (slug => full page data), rendered by landing::product.
     * Ported verbatim from the legacy Marketing\ProductController catalog.
     */
    'products' => [
        'virtual-card' => [
            'eyebrow' => 'Virtual Card',
            'icon' => 'credit-card',
            'title' => 'Spend crypto like cash, anywhere',
            'lead' => 'Issue a virtual card in seconds and pay online or tap-to-pay in-store. It spends straight from your stablecoin balance and converts at the point of sale — no top-ups, no monthly fee, just a flat 1% per transaction with limits and freeze in your control.',
            'highlights' => [
                'Issue in seconds — no application',
                'Spend at millions of merchants',
                'Freeze & set limits anytime',
                'No monthly or annual fee',
            ],
            'features' => [
                ['icon' => 'bolt', 'title' => 'Instant issuance', 'desc' => 'Generate a card the moment you need it — no application and no delivery wait. Issue more than one for different budgets.'],
                ['icon' => 'arrows-right-left', 'title' => 'Auto-converts at checkout', 'desc' => 'Spends from your stablecoin balance and converts to the merchant’s currency at the point of sale — you hold crypto, you pay in their money.'],
                ['icon' => 'adjustments-horizontal', 'title' => 'Limits & controls', 'desc' => 'Set daily and per-transaction caps, allow specific countries and block merchant categories — change any of it instantly.'],
                ['icon' => 'bell-alert', 'title' => 'Real-time authorizations', 'desc' => 'Every authorization and settlement posts to your ledger the instant it happens, with a notification you can act on.'],
                ['icon' => 'device-phone-mobile', 'title' => 'Tap to pay', 'desc' => 'Add the card to your mobile wallet and tap to pay in-store, anywhere contactless is accepted.'],
                ['icon' => 'shield-check', 'title' => 'Protection & disputes', 'desc' => 'Transactions are risk-scored and monitored for fraud, and you can open a dispute on any charge from your dashboard.'],
            ],
            'grid' => [
                'eyebrow' => 'Total control',
                'title' => 'Your card, your rules',
                'lead' => 'Tune exactly how, where and how much your card can spend — all from one screen.',
                'items' => [
                    ['icon' => 'lock-closed', 'name' => 'Freeze instantly', 'desc' => 'Pause and resume the card in a tap — no waiting, no phone call.'],
                    ['icon' => 'banknotes', 'name' => 'Daily spend limit', 'desc' => 'Cap how much can be spent in a single day.'],
                    ['icon' => 'credit-card', 'name' => 'Per-transaction cap', 'desc' => 'Set the largest amount any one charge can be.'],
                    ['icon' => 'globe-alt', 'name' => 'Country controls', 'desc' => 'Allow spending only in the countries you choose.'],
                    ['icon' => 'building-storefront', 'name' => 'Block merchant types', 'desc' => 'Turn off categories you never want to pay for.'],
                    ['icon' => 'key', 'name' => 'Set a PIN & replace', 'desc' => 'Choose a PIN, or replace a card without losing its settings.'],
                ],
            ],
            'steps' => [
                ['icon' => 'credit-card', 'title' => 'Issue your card', 'desc' => 'Open your dashboard and create a virtual card in a single tap.'],
                ['icon' => 'wallet', 'title' => 'Keep crypto funded', 'desc' => 'Hold any supported stablecoin in your wallet — it becomes your spend balance.'],
                ['icon' => 'shopping-bag', 'title' => 'Pay anywhere', 'desc' => 'Use it online, or add it to your phone and tap to pay in-store.'],
                ['icon' => 'adjustments-horizontal', 'title' => 'Stay in control', 'desc' => 'Freeze, set limits and watch every authorization in real time.'],
            ],
            'faqs' => [
                ['q' => 'Is it a virtual or physical card?', 'a' => 'You get a virtual card you can use online and add to your mobile wallet to tap to pay in-store immediately. A physical card can also be issued if enabled on your account.'],
                ['q' => 'Which balance does it spend from?', 'a' => 'It draws from your PaishaPay stablecoin balance and converts your asset to the merchant’s currency at the point of sale, so you can spend crypto without pre-converting it.'],
                ['q' => 'What does it cost?', 'a' => 'There is no monthly or annual fee. A flat 1% is applied per transaction, and any one-time issuance price (if your operator sets one) is shown before you create the card.'],
                ['q' => 'Can I freeze the card or set limits?', 'a' => 'Yes. Freeze and unfreeze instantly, set daily and per-transaction limits, restrict spending by country and block merchant categories — all from your dashboard, any time.'],
                ['q' => 'What if a charge is wrong?', 'a' => 'Every transaction is risk-scored and monitored for fraud. If something looks wrong you can freeze the card and open a dispute on the charge directly from your dashboard.'],
                ['q' => 'Can I have more than one card?', 'a' => 'Yes — issue multiple virtual cards with their own limits and controls, for example one for subscriptions and one for day-to-day spending.'],
            ],
        ],
        'wallet' => [
            'eyebrow' => 'Wallet',
            'icon' => 'wallet',
            'title' => 'One balance across every network',
            'lead' => 'Hold, receive and send crypto and fiat from a single wallet. USDT on eight chains is one pooled balance — the network only matters when you deposit or withdraw. Send to any PaishaPay user instantly and free, valued live in your own currency.',
            'highlights' => [
                'One pooled balance per coin',
                'Free, instant internal transfers',
                'Deposit & withdraw across 8 chains',
                'Cold-storage custody',
            ],
            'features' => [
                ['icon' => 'link', 'title' => 'One balance per coin', 'desc' => 'Hold USDT on Tron, Ethereum, BNB Chain and more, yet see and spend one pooled balance — no juggling the same asset across chains.'],
                ['icon' => 'paper-airplane', 'title' => 'Instant, free transfers', 'desc' => 'Send to any PaishaPay user in seconds with zero fee — it settles on our internal ledger, not on-chain, so it never waits for a block.'],
                ['icon' => 'arrow-down-tray', 'title' => 'On-chain deposits', 'desc' => 'Get a unique deposit address per network and fund your wallet from any external wallet or exchange.'],
                ['icon' => 'squares-2x2', 'title' => 'Crypto & fiat together', 'desc' => 'Manage stablecoins, native coins and fiat side by side, each valued live in your chosen base currency.'],
                ['icon' => 'arrows-right-left', 'title' => 'Swap & spend', 'desc' => 'Convert between assets at a transparent rate and spend straight from your balance with a PaishaPay card.'],
                ['icon' => 'shield-check', 'title' => 'Custodial security', 'desc' => 'The majority of crypto is held in offline cold storage, with continuous reconciliation against on-chain holdings.'],
            ],
            'grid' => [
                'eyebrow' => 'Deposit & withdraw',
                'title' => 'Eight networks, one wallet',
                'lead' => 'Move assets on the chain that suits you — your in-app balance stays pooled per coin.',
                'items' => [
                    ['icon' => 'bolt', 'name' => 'Tron (TRC-20)', 'desc' => 'Low-cost USDT and USDC transfers.'],
                    ['icon' => 'cube-transparent', 'name' => 'Ethereum', 'desc' => 'USDT, USDC and ETH on mainnet.'],
                    ['icon' => 'fire', 'name' => 'BNB Smart Chain', 'desc' => 'Fast, cheap BEP-20 transfers.'],
                    ['icon' => 'square-3-stack-3d', 'name' => 'Polygon', 'desc' => 'Low-fee stablecoin deposits.'],
                    ['icon' => 'bolt-slash', 'name' => 'Arbitrum · Optimism · Base', 'desc' => 'Ethereum L2s with low fees.'],
                    ['icon' => 'globe-alt', 'name' => 'Avalanche', 'desc' => 'C-Chain stablecoins and AVAX.'],
                ],
            ],
            'steps' => [
                ['icon' => 'arrow-down-tray', 'title' => 'Add funds', 'desc' => 'Deposit crypto to your unique per-network address, or top up your fiat balance.'],
                ['icon' => 'squares-2x2', 'title' => 'See everything in one place', 'desc' => 'Track every asset, its live value and full history from a single dashboard.'],
                ['icon' => 'paper-airplane', 'title' => 'Send, swap or spend', 'desc' => 'Transfer to friends instantly, swap between assets, or spend with your card.'],
                ['icon' => 'arrow-up-tray', 'title' => 'Withdraw anytime', 'desc' => 'Cash out on-chain to any external address on the network you choose.'],
            ],
            'faqs' => [
                ['q' => 'What does "one balance per coin" mean?', 'a' => 'If you hold USDT on several networks, PaishaPay shows and spends it as a single pooled balance. The network only matters at the moment you deposit or withdraw on-chain.'],
                ['q' => 'Which coins and networks are supported?', 'a' => 'Stablecoins USDT and USDC plus native assets, across eight networks — Tron, Ethereum, BNB Smart Chain, Polygon, Arbitrum, Optimism, Base and Avalanche — alongside fiat balances.'],
                ['q' => 'Are internal transfers really free?', 'a' => 'Yes. Sending to another PaishaPay user is instant and fee-free because it settles on our internal ledger rather than on-chain.'],
                ['q' => 'What are the deposit and withdrawal fees?', 'a' => 'Deposits are free. Withdrawals carry a small percentage platform fee, shown before you confirm — PaishaPay absorbs the network gas cost.'],
                ['q' => 'How are my assets kept safe?', 'a' => 'Balances are held custodially, with the majority of crypto in offline cold storage and continuous reconciliation against on-chain holdings. Every balance is derived from an immutable double-entry ledger.'],
                ['q' => 'Can I see my balance in my own currency?', 'a' => 'Yes — choose a base currency and every asset is valued live in it, so you always know what your wallet is worth at a glance.'],
            ],
        ],
        'exchange' => [
            'eyebrow' => 'Exchange',
            'icon' => 'arrows-right-left',
            'title' => 'Swap currencies in seconds',
            'lead' => 'Convert between crypto and fiat at live market rates with the spread shown up front. You lock a quote, confirm, and it settles to your balance instantly — no order books, no slippage, no surprises.',
            'highlights' => [
                'Live market rates',
                'Spread shown before you confirm',
                'Instant on-ledger settlement',
                'Crypto ↔ fiat, both ways',
            ],
            'features' => [
                ['icon' => 'bolt', 'title' => 'Instant swaps', 'desc' => 'Convert one asset to another in a single tap — settled to your balance the moment you confirm, not minutes later.'],
                ['icon' => 'scale', 'title' => 'Transparent pricing', 'desc' => 'The rate and spread are shown before you confirm. What you see is exactly what lands — no hidden markup.'],
                ['icon' => 'lock-closed', 'title' => 'Locked quote', 'desc' => 'Each quote holds a fixed rate for a few seconds so the amount you receive can’t move between review and confirm.'],
                ['icon' => 'clock', 'title' => 'Live market rates', 'desc' => 'Prices track live market data continuously, so every swap is priced at a current rate.'],
                ['icon' => 'shield-check', 'title' => 'Settled on-ledger', 'desc' => 'Every conversion posts a balanced entry on our immutable double-entry ledger — fully auditable.'],
                ['icon' => 'arrows-right-left', 'title' => 'Crypto ↔ fiat', 'desc' => 'Move seamlessly between stablecoins, native coins and your local fiat currency in either direction.'],
            ],
            'grid' => [
                'eyebrow' => 'Any direction',
                'title' => 'What you can swap',
                'lead' => 'Convert across stablecoins, native crypto and fiat — all inside your wallet.',
                'items' => [
                    ['icon' => 'banknotes', 'name' => 'Stablecoin ↔ fiat', 'desc' => 'Move between USDT/USDC and your local currency.'],
                    ['icon' => 'currency-dollar', 'name' => 'Stablecoin ↔ crypto', 'desc' => 'Swap USDT into ETH, BNB and other assets.'],
                    ['icon' => 'arrow-path', 'name' => 'Crypto ↔ crypto', 'desc' => 'Rebalance directly between digital assets.'],
                    ['icon' => 'globe-alt', 'name' => 'Fiat ↔ crypto', 'desc' => 'Buy into crypto straight from a fiat balance.'],
                    ['icon' => 'scale', 'name' => 'Per-pair pricing', 'desc' => 'Each pair carries its own transparent spread.'],
                    ['icon' => 'credit-card', 'name' => 'Powers card spend', 'desc' => 'The same engine converts at the point of sale.'],
                ],
            ],
            'steps' => [
                ['icon' => 'arrows-right-left', 'title' => 'Choose your pair', 'desc' => 'Pick the asset to swap from and the one to receive.'],
                ['icon' => 'scale', 'title' => 'Review the quote', 'desc' => 'See the exact amount you’ll receive, spread included, before you commit.'],
                ['icon' => 'lock-closed', 'title' => 'Lock the rate', 'desc' => 'Confirm within the quote window to secure the shown rate.'],
                ['icon' => 'check-circle', 'title' => 'Settled instantly', 'desc' => 'The swap posts to your balance on our ledger the moment you confirm.'],
            ],
            'faqs' => [
                ['q' => 'How is the exchange rate set?', 'a' => 'Rates come from live market data with a small, transparent spread that is always shown before you confirm. Some pairs carry their own spread, and it is always disclosed in the quote.'],
                ['q' => 'Will the rate change after I see it?', 'a' => 'No. Each quote locks a fixed rate for a few seconds. As long as you confirm within that window, the amount you receive is exactly what was shown.'],
                ['q' => 'How fast are swaps?', 'a' => 'Instant. They settle to your PaishaPay balance the moment you confirm, on our internal ledger — there is no on-chain wait.'],
                ['q' => 'Can I swap between crypto and fiat?', 'a' => 'Yes, in either direction — stablecoin to fiat, fiat to crypto, or crypto to crypto — all from your wallet.'],
                ['q' => 'Are there limits on a swap?', 'a' => 'Some pairs have a minimum and maximum amount per swap, and larger conversions may require a completed KYC level. Any limit is shown when you build the quote.'],
                ['q' => 'What does a swap cost?', 'a' => 'The cost is the spread built into the quoted rate — there is nothing hidden on top. You always see the net amount you will receive before confirming.'],
            ],
        ],
        'shop' => [
            'eyebrow' => 'Shop',
            'icon' => 'building-storefront',
            'title' => 'Sell anything, get paid instantly',
            'lead' => 'Build a high-converting sales page with a real drag-and-drop editor, then sell digital or physical products with wallet, card and crypto checkout. Order bumps, 1-click upsells and coupons are built in — commission drops to as low as 3% as you grow, and every sale settles straight to your PaishaPay balance.',
            'cta' => ['label' => 'Start selling', 'route' => 'shop'],
            'highlights' => [
                'No monthly fee on the Free plan',
                'Payouts settle to your balance',
                'Wallet · card · crypto checkout',
                'KYC & AML protected',
            ],
            'features' => [
                ['icon' => 'squares-2x2', 'title' => 'Visual page builder', 'desc' => 'Design sales pages with a real drag-and-drop block editor — hero, features, testimonials, FAQ, checkout. Draft, preview, publish and roll back with saved revisions. No code.'],
                ['icon' => 'credit-card', 'title' => 'Instant checkout', 'desc' => 'Buyers pay with their PaishaPay wallet, a card or crypto. Funds settle straight to your balance — no external gateway, no payout waiting on a bank.'],
                ['icon' => 'arrow-trending-up', 'title' => 'Bumps & upsells', 'desc' => 'Lift average order value with pre-purchase order bumps and 1-click post-purchase upsells — no re-entering payment details.'],
                ['icon' => 'ticket', 'title' => 'Coupons & discounts', 'desc' => 'Run percentage or fixed-amount coupons with usage caps and expiry dates to launch promos and recover carts.'],
                ['icon' => 'globe-alt', 'title' => 'Custom domains', 'desc' => 'Connect your own domain and publish a fully branded storefront and checkout — your name in the address bar, not ours.'],
                ['icon' => 'chart-bar', 'title' => 'Orders & analytics', 'desc' => 'Track views, conversion and revenue per page and funnel step, manage orders, refunds, reviews and customers from one dashboard.'],
            ],
            'grid' => [
                'eyebrow' => 'One dashboard',
                'title' => 'Sell any kind of product',
                'lead' => 'Digital or physical, one-off or recurring — pick a type and start selling.',
                'items' => [
                    ['icon' => 'arrow-down-tray', 'name' => 'Digital downloads', 'desc' => 'Files, PDFs, assets — delivered instantly on payment.'],
                    ['icon' => 'key', 'name' => 'License keys', 'desc' => 'Issue and manage keys for software and tools.'],
                    ['icon' => 'user-group', 'name' => 'Memberships', 'desc' => 'Gate access and content behind a paid membership.'],
                    ['icon' => 'arrow-path', 'name' => 'Subscriptions', 'desc' => 'Recurring billing for ongoing products and plans.'],
                    ['icon' => 'wrench-screwdriver', 'name' => 'Services', 'desc' => 'Sell your time, consulting or done-for-you work.'],
                    ['icon' => 'cube', 'name' => 'Physical goods', 'desc' => 'Ship products with shipping and address capture.'],
                    ['icon' => 'squares-plus', 'name' => 'Bundles', 'desc' => 'Package multiple products into one offer.'],
                ],
            ],
            'steps' => [
                ['icon' => 'identification', 'title' => 'Apply to sell', 'desc' => 'Complete a short seller application with KYC. Once approved, your Shop dashboard unlocks.'],
                ['icon' => 'cube', 'title' => 'Add your product', 'desc' => 'Create a digital or physical product, set the price in your own currency and pick how it settles.'],
                ['icon' => 'squares-2x2', 'title' => 'Build your page', 'desc' => 'Drag in blocks, add order bumps and upsells, then publish to a PaishaPay link or your custom domain.'],
                ['icon' => 'banknotes', 'title' => 'Share & get paid', 'desc' => 'Buyers check out with wallet, card or crypto and your net lands in your balance after the refund window.'],
            ],
            'faqs' => [
                ['q' => 'What does it cost to sell?', 'a' => 'Nothing to start — the Free plan has no monthly fee, one sales page and 8% commission per sale. Pro is $10/month for up to 3 products at 5% commission, and Business is $20/month for up to 10 products at 3% commission. There are no separate payment-processing fees.'],
                ['q' => 'What can I sell?', 'a' => 'Seven product types: digital downloads, license keys, memberships, subscriptions, services, physical goods and bundles. Physical products support shipping and address capture at checkout.'],
                ['q' => 'How and when do I get paid?', 'a' => 'Payments settle straight to your PaishaPay balance — no external gateway. Earnings can be held for the buyer’s refund window and then released automatically to your spendable balance, so refunds never leave you in the negative.'],
                ['q' => 'How do buyers pay?', 'a' => 'Every checkout supports three rails: a PaishaPay wallet balance, a card, or crypto. Buyers pick whichever they have — you receive your settlement asset either way.'],
                ['q' => 'Do I need to code?', 'a' => 'No. The page builder is fully visual — drag blocks, edit inline, preview and publish. Saved revisions let you roll back any change.'],
                ['q' => 'Can I use my own domain?', 'a' => 'Yes. Connect a custom domain to publish a branded storefront and checkout under your own name.'],
                ['q' => 'How are refunds handled?', 'a' => 'You can approve refunds from your dashboard. A refund reverses the original ledger entries, revokes access to digital products and returns the buyer’s money — fully audited end to end.'],
            ],
            'pricing' => [
                [
                    'name' => 'Free',
                    'price' => '৳0',
                    'period' => 'forever',
                    'tagline' => 'Everything you need to make your first sale.',
                    'features' => ['1 sales page', '8% commission per sale', 'Wallet, card & crypto checkout', 'Instant settlement to your balance'],
                ],
                [
                    'name' => 'Pro',
                    'price' => '$10',
                    'period' => 'per month',
                    'tagline' => 'For creators growing a real catalog.',
                    'popular' => true,
                    'features' => ['Up to 3 products', '5% commission per sale', 'Everything in Free', 'Order bumps, upsells & coupons', 'Custom domain'],
                ],
                [
                    'name' => 'Business',
                    'price' => '$20',
                    'period' => 'per month',
                    'tagline' => 'Lowest rate, built for scale.',
                    'features' => ['Up to 10 products', '3% commission per sale', 'Everything in Pro', 'Lowest commission rate'],
                ],
            ],
        ],
        'merchant-pay' => [
            'eyebrow' => 'Merchant Pay',
            'icon' => 'building-storefront',
            'title' => 'Accept crypto, settle instantly',
            'lead' => 'Send a payment request and get paid in USDT, BTC or ETH. Every payment settles straight to your PaishaPay wallet the moment it clears — a flat 1% fee, no chargebacks, and a webhook for every paid invoice. Approval is instant, so you can start charging today.',
            'cta' => ['label' => 'Start accepting', 'route' => 'merchant'],
            'highlights' => [
                'Flat 1% per payment',
                'Settles instantly to your wallet',
                'No chargebacks, ever',
                'Instant merchant approval',
            ],
            'features' => [
                ['icon' => 'document-text', 'title' => 'Payment requests', 'desc' => 'Create an invoice in seconds, share the link or show a QR — the customer pays in the crypto they hold.'],
                ['icon' => 'bolt', 'title' => 'Instant settlement', 'desc' => 'The moment a payment clears, the net lands in your PaishaPay wallet — no multi-day payout wait, no bank in the middle.'],
                ['icon' => 'banknotes', 'title' => 'Flat 1% fee', 'desc' => 'One simple processing fee per payment. No setup cost, no monthly fee, no per-coin surcharge.'],
                ['icon' => 'shield-check', 'title' => 'No chargebacks', 'desc' => 'Crypto payments are final once settled, so you keep what you earn — no fraud reversals weeks later.'],
                ['icon' => 'code-bracket', 'title' => 'Webhooks & API', 'desc' => 'Every paid invoice fires an invoice.paid event, so you can automate fulfilment, receipts and reconciliation.'],
                ['icon' => 'arrow-uturn-left', 'title' => 'Refunds & control', 'desc' => 'Issue a refund, cancel an unpaid invoice, and track every payment from your merchant dashboard.'],
            ],
            'grid' => [
                'eyebrow' => 'One account',
                'title' => 'Every way to get paid',
                'lead' => 'Charge however suits your business — online, in person or programmatically.',
                'items' => [
                    ['icon' => 'link', 'name' => 'Payment links', 'desc' => 'Share a link on WhatsApp, social or email.'],
                    ['icon' => 'qr-code', 'name' => 'QR at checkout', 'desc' => 'Show a code in-store — scan and pay in a tap.'],
                    ['icon' => 'document-text', 'name' => 'Invoices', 'desc' => 'Send itemised requests with a reference.'],
                    ['icon' => 'currency-dollar', 'name' => 'Multi-coin', 'desc' => 'Accept USDT, USDC, BTC, ETH and more.'],
                    ['icon' => 'bolt', 'name' => 'Instant payout', 'desc' => 'Funds settle to your wallet on payment.'],
                    ['icon' => 'code-bracket', 'name' => 'API & webhooks', 'desc' => 'Automate orders and reconciliation.'],
                ],
            ],
            'steps' => [
                ['icon' => 'user-plus', 'title' => 'Create & verify', 'desc' => 'Register your business and complete KYC once — approval is instant.'],
                ['icon' => 'document-text', 'title' => 'Send a request', 'desc' => 'Create a payment request and share the link or QR with your customer.'],
                ['icon' => 'wallet', 'title' => 'Customer pays', 'desc' => 'They pay in the crypto they hold — from any wallet or exchange.'],
                ['icon' => 'bolt', 'title' => 'Settle instantly', 'desc' => 'Your net lands in your PaishaPay balance the moment it clears.'],
            ],
            'faqs' => [
                ['q' => 'What does it cost to accept payments?', 'a' => 'A flat 1% processing fee per payment. There is no setup cost, no monthly fee and no per-coin surcharge — the fee is shown on every invoice.'],
                ['q' => 'How fast do I get paid?', 'a' => 'Instantly. When a customer pays, the net amount settles straight to your PaishaPay wallet on our internal ledger — there is no multi-day payout or bank delay.'],
                ['q' => 'Which coins can customers pay with?', 'a' => 'Stablecoins USDT and USDC plus major assets like BTC and ETH. The customer pays in whatever they hold and you receive your settlement asset.'],
                ['q' => 'Are there chargebacks?', 'a' => 'No. Once a crypto payment settles it is final, so you are protected from the fraud reversals common with card payments.'],
                ['q' => 'Can I refund a customer?', 'a' => 'Yes. You can refund a paid invoice or cancel an unpaid one directly from your merchant dashboard, and the ledger records every step.'],
                ['q' => 'Can I automate my checkout?', 'a' => 'Yes. Every paid invoice emits an invoice.paid webhook, so you can trigger fulfilment, send receipts and reconcile automatically through the API.'],
                ['q' => 'How long does approval take?', 'a' => 'Merchant approval is instant on sign-up, so you can create your first payment request and start charging the same day.'],
            ],
        ],
    ],

];
