<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about-us',
                'title' => 'About PoishaPay',
                'meta_description' => 'PoishaPay is a multi-chain wallet built for Bangladesh — hold, send and exchange crypto and Taka in one secure app.',
                'content' => <<<'HTML'
<p>PoishaPay is a multi-chain wallet built for Bangladesh. We let you hold crypto and Taka side by side, send money to anyone instantly, and spend your balance with virtual cards — all from one app.</p>
<h2>Our mission</h2>
<p>We believe moving money should be as easy as sending a message. PoishaPay brings together digital assets and everyday payments so that anyone, anywhere in Bangladesh, can participate in the global economy without friction.</p>
<h2>How we keep you safe</h2>
<p>Every account is protected by custodial cold storage, two-factor authentication, and KYC/AML checks. We never expose your private keys, and our operations team monitors the platform around the clock.</p>
<h2>Built for the community</h2>
<p>From instant peer-to-peer transfers with zero fees to a built-in exchange with transparent pricing, every feature is designed to serve the people who use it. We are just getting started.</p>
HTML,
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'meta_description' => 'The terms and conditions that govern your use of PoishaPay.',
                'content' => <<<'HTML'
<p>By creating an account and using PoishaPay, you agree to these Terms of Service. Please read them carefully.</p>
<h2>1. Eligibility</h2>
<p>You must be at least 18 years old and a resident of a jurisdiction where PoishaPay is available. You agree to provide accurate information and to complete identity verification when required.</p>
<h2>2. Your account</h2>
<p>You are responsible for keeping your login credentials confidential and for all activity that occurs under your account. Notify us immediately if you suspect unauthorised access.</p>
<h2>3. Acceptable use</h2>
<p>You may not use PoishaPay for money laundering, fraud, or any unlawful activity. We reserve the right to suspend or close accounts that violate these terms or applicable law.</p>
<h2>4. Fees and limits</h2>
<p>Fees and transaction limits are disclosed in the app before you confirm a transaction. We may adjust them from time to time and will notify you of material changes.</p>
<h2>5. Liability</h2>
<p>PoishaPay is provided on an "as is" basis. To the fullest extent permitted by law, we are not liable for losses arising from market movements, network delays, or events beyond our reasonable control.</p>
HTML,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta_description' => 'How PoishaPay collects, uses, and protects your personal information.',
                'content' => <<<'HTML'
<p>This Privacy Policy explains how PoishaPay collects, uses, and protects your personal information.</p>
<h2>Information we collect</h2>
<p>We collect the details you provide when you register, such as your name, email, phone number, and identity documents, along with transaction data generated as you use the service.</p>
<h2>How we use your data</h2>
<p>Your information is used to operate your account, process transactions, comply with anti-money-laundering obligations, prevent fraud, and improve our products. We do not sell your personal data.</p>
<h2>Sharing and disclosure</h2>
<p>We share data only with service providers who help us run the platform and with regulators or law enforcement where we are legally required to do so.</p>
<h2>Security and retention</h2>
<p>We apply encryption, access controls, and continuous monitoring to protect your data, and we retain it only for as long as necessary to meet legal and operational requirements.</p>
<h2>Your rights</h2>
<p>You may request access to, correction of, or deletion of your personal data, subject to legal limits. Contact our support team to exercise these rights.</p>
HTML,
            ],
            [
                'slug' => 'legal',
                'title' => 'Legal & Policies',
                'meta_description' => 'PoishaPay legal centre — Terms of Service, Privacy Policy, AML & KYC Policy, and our compliance approach.',
                'content' => <<<'HTML'
<p>This page brings together the policies that govern your use of PoishaPay. Use the footer links to jump to a section. These policies may be updated from time to time; we will notify you of material changes through the app or by email.</p>

<h2 id="terms">Terms of Service</h2>
<p>By creating an account and using PoishaPay, you agree to these Terms of Service. Please read them carefully, together with the Privacy and AML &amp; KYC policies below.</p>
<h3>1. Eligibility</h3>
<p>You must be at least 18 years old and a resident of a jurisdiction where PoishaPay is available. You agree to provide accurate, current information and to complete identity verification when required.</p>
<h3>2. Your account</h3>
<p>You are responsible for keeping your login credentials confidential and for all activity that occurs under your account. Enable two-factor authentication and notify us immediately if you suspect unauthorised access.</p>
<h3>3. Acceptable use</h3>
<p>You may not use PoishaPay for money laundering, terrorist financing, fraud, sanctions evasion, or any unlawful activity. We may suspend, restrict, or close accounts that violate these terms or applicable law.</p>
<h3>4. Digital assets &amp; custody</h3>
<p>Crypto balances are held custodially on your behalf. Digital assets are volatile and their value can fall as well as rise. You are responsible for the tax consequences of your transactions in your jurisdiction.</p>
<h3>5. Fees and limits</h3>
<p>Fees and transaction limits are disclosed in the app before you confirm a transaction. We may adjust them from time to time and will give notice of material changes.</p>
<h3>6. Liability</h3>
<p>PoishaPay is provided on an "as is" basis. To the fullest extent permitted by law, we are not liable for losses arising from market movements, network delays, or events beyond our reasonable control.</p>

<h2 id="privacy">Privacy Policy</h2>
<p>This Privacy Policy explains how PoishaPay collects, uses, and protects your personal information.</p>
<h3>Information we collect</h3>
<p>We collect the details you provide when you register — such as your name, email, phone number, and identity documents — along with transaction data generated as you use the service, and technical data such as device and log information.</p>
<h3>How we use your data</h3>
<p>Your information is used to operate your account, process transactions, comply with anti-money-laundering obligations, prevent fraud, and improve our products. We do not sell your personal data.</p>
<h3>Sharing and disclosure</h3>
<p>We share data only with service providers who help us run the platform, and with regulators or law enforcement where we are legally required to do so.</p>
<h3>Security and retention</h3>
<p>We apply encryption, access controls, and continuous monitoring to protect your data, and we retain it only for as long as necessary to meet legal and operational requirements.</p>
<h3>Your rights</h3>
<p>You may request access to, correction of, or deletion of your personal data, subject to legal limits. Contact <strong>support@poisapay.com</strong> to exercise these rights.</p>

<h2 id="aml-kyc">AML &amp; KYC Policy</h2>
<p>PoishaPay is committed to preventing money laundering and the financing of illegal activity. We maintain a risk-based anti-money-laundering (AML) programme and cooperate with lawful requests from competent authorities.</p>
<h3>Know Your Customer</h3>
<p>Identity verification (KYC) is required to open and operate a PoishaPay account. We collect and verify identity information such as your name, date of birth, address, and government-issued identification. Higher limits and certain features may require additional verification tiers.</p>
<h3>Monitoring</h3>
<p>We monitor transactions for unusual or suspicious activity, apply risk-based limits, and screen against applicable sanctions and watchlists. We may request additional information or documentation, and file reports with regulators where required.</p>
<h3>Ongoing review</h3>
<p>We may periodically re-verify your information and request updated documents to keep your account in good standing.</p>

<h2 id="compliance">Compliance</h2>
<p>PoishaPay operates as a custodial platform and applies KYC/AML controls, transaction monitoring, and risk-based limits in line with applicable regulations in the jurisdictions we serve.</p>
<h3>Custody &amp; controls</h3>
<p>Customer balances are held custodially, with the majority of crypto assets kept in offline cold storage and internal reconciliation controls in place.</p>
<h3>Availability</h3>
<p>Some products and services may not be available in all jurisdictions. Card services are provided by licensed issuing partners where applicable.</p>
<h3>Contact</h3>
<p>For compliance or legal enquiries, contact <strong>compliance@poisapay.com</strong>.</p>
HTML,
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact Us',
                'meta_description' => 'Get in touch with the PoishaPay team — support, business and press enquiries.',
                'content' => <<<'HTML'
<p>We're here to help. Reach the team through any of the channels below and we'll get back to you as soon as we can.</p>
<h2>Support</h2>
<p>Signed in? Open a ticket from the in-app Support centre for the fastest response. You can also email <strong>support@poisapay.com</strong>.</p>
<h2>Business &amp; partnerships</h2>
<p>Interested in accepting crypto payments or partnering with us? Email <strong>business@poisapay.com</strong>.</p>
<h2>Press</h2>
<p>For media enquiries, contact <strong>press@poisapay.com</strong>.</p>
HTML,
            ],
            [
                'slug' => 'careers',
                'title' => 'Careers',
                'meta_description' => 'Join PoishaPay and help build the future of borderless payments.',
                'content' => <<<'HTML'
<p>We're a small, focused team building borderless payments powered by digital assets. If you care about craft, security, and making money move effortlessly, we'd love to hear from you.</p>
<h2>How we work</h2>
<p>We value ownership, clear communication, and shipping quality work. Everyone here works close to the product and the people who use it.</p>
<h2>Open roles</h2>
<p>We're always interested in talented engineers, designers, and compliance specialists. Send your CV and a note about what you'd like to work on to <strong>careers@poisapay.com</strong>.</p>
HTML,
            ],
            [
                'slug' => 'blog',
                'title' => 'Blog',
                'meta_description' => 'Product updates, security announcements and insights from the PoishaPay team.',
                'content' => <<<'HTML'
<p>The PoishaPay blog is where we share product updates, new features, and important security announcements.</p>
<p>We're preparing our first posts now. In the meantime, follow us on social media for the latest news, or check the <a href="/status">system status</a> page for live service updates.</p>
HTML,
            ],
            [
                'slug' => 'api',
                'title' => 'API Documentation',
                'meta_description' => 'Build on PoishaPay with our REST API and webhooks.',
                'content' => <<<'HTML'
<p>Build on PoishaPay with a clean REST API and webhooks — automate deposits, withdrawals, transfers and merchant invoicing directly from your own systems.</p>
<h2>What you can do</h2>
<p>Create invoices and payment links, query balances and transactions, and receive real-time webhook notifications for payments and settlements.</p>
<h2>Access</h2>
<p>The full public reference is on its way. For early access and API keys, contact <strong>business@poisapay.com</strong>.</p>
HTML,
            ],
            [
                'slug' => 'aml-kyc',
                'title' => 'AML & KYC Policy',
                'meta_description' => 'How PoishaPay prevents money laundering and verifies customer identity (AML & KYC).',
                'content' => <<<'HTML'
<p>PoishaPay is committed to preventing money laundering, terrorist financing, and financial crime. This AML &amp; KYC Policy explains the controls we apply to every account.</p>
<h2>1. Know Your Customer (KYC)</h2>
<p>Before you can transact, we verify your identity using government-issued documents and, where required, proof of address and a liveness check. Higher transaction tiers require additional verification.</p>
<h2>2. Customer due diligence</h2>
<p>We risk-rate every customer and apply enhanced due diligence to higher-risk profiles. We may request the source of funds or the purpose of a transaction at any time.</p>
<h2>3. Transaction monitoring</h2>
<p>All activity is monitored in real time against risk rules and sanctions/denylists. Unusual or suspicious activity is automatically flagged for review and may result in holds, limits, or account freezes.</p>
<h2>4. Sanctions screening</h2>
<p>Customers and counterparties are screened against applicable sanctions lists. We do not provide services to sanctioned individuals, entities, or jurisdictions.</p>
<h2>5. Suspicious activity reporting</h2>
<p>Where legally required, we report suspicious activity to the relevant authorities. We cooperate fully with regulators and law enforcement.</p>
<h2>6. Record keeping</h2>
<p>We retain identity and transaction records for the period required by law, and keep them secure and confidential.</p>
HTML,
            ],
            [
                'slug' => 'compliance',
                'title' => 'Compliance',
                'meta_description' => 'PoishaPay’s regulatory compliance framework, governance, and how to reach our compliance team.',
                'content' => <<<'HTML'
<p>Compliance is central to how PoishaPay operates. We build our products to meet applicable financial-services, data-protection, and consumer-protection obligations.</p>
<h2>Our framework</h2>
<p>Our compliance programme covers AML/CFT, sanctions screening, KYC, fraud prevention, risk management, and data protection. Policies are reviewed regularly and approved by senior management.</p>
<h2>Governance</h2>
<p>A dedicated compliance function oversees the programme independently of commercial teams, with clear escalation paths and board-level accountability.</p>
<h2>Customer protection</h2>
<p>Customer funds are safeguarded, balances are derived from an immutable double-entry ledger, and every money movement is audited end to end.</p>
<h2>Reporting a concern</h2>
<p>To report a compliance concern, request information, or submit a law-enforcement request, contact <strong>compliance@poisapay.com</strong>.</p>
<h2>Related policies</h2>
<p>See our <a href="/pages/aml-kyc">AML &amp; KYC Policy</a>, <a href="/pages/terms">Terms of Service</a>, and <a href="/pages/privacy">Privacy Policy</a>.</p>
HTML,
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'meta_description' => $page['meta_description'],
                    'status' => 'published',
                ],
            );
        }

        // Curated homepage set (show_on_homepage) plus the per-page FAQs surfaced on
        // each marketing product page — one source of truth for the Help Center.
        $faqs = [
            // ── Getting started (homepage) ──
            [
                'question' => 'How do I make a deposit?',
                'answer' => 'Go to the Deposit screen, choose the asset and network you want to fund, and send crypto to the address shown. Once the network confirms your transaction, the balance is credited to your wallet automatically.',
                'group' => 'Deposits',
                'sort_order' => 10,
                'show_on_homepage' => true,
            ],
            [
                'question' => 'How long do withdrawals take?',
                'answer' => 'Withdrawal requests are reviewed and broadcast to the blockchain shortly after you confirm them. On-chain settlement depends on network congestion, but most withdrawals complete within a few minutes.',
                'group' => 'Withdrawals',
                'sort_order' => 20,
                'show_on_homepage' => true,
            ],
            [
                'question' => 'Why do I need to verify my identity (KYC)?',
                'answer' => 'Identity verification protects you and the platform from fraud and helps us meet anti-money-laundering rules. Some features, such as virtual cards and higher limits, are unlocked only after your verification is approved.',
                'group' => 'Verification',
                'sort_order' => 30,
                'show_on_homepage' => true,
            ],
            [
                'question' => 'What fees does PoishaPay charge?',
                'answer' => 'Peer-to-peer transfers between PoishaPay users are free. Deposits, withdrawals, and exchanges may carry a small network or spread fee, which is always shown transparently before you confirm the transaction.',
                'group' => 'Fees',
                'sort_order' => 40,
                'show_on_homepage' => true,
            ],

            // ── Wallet ──
            [
                'question' => 'What does "one balance per coin" mean?',
                'answer' => 'If you hold USDT on several networks, PoishaPay shows and spends it as a single pooled balance. The network only matters at the moment you deposit or withdraw on-chain.',
                'group' => 'Wallet',
                'sort_order' => 100,
            ],
            [
                'question' => 'Which coins and networks are supported?',
                'answer' => 'Stablecoins USDT and USDC plus native assets, across eight networks — Tron, Ethereum, BNB Smart Chain, Polygon, Arbitrum, Optimism, Base and Avalanche — alongside fiat balances.',
                'group' => 'Wallet',
                'sort_order' => 105,
            ],
            [
                'question' => 'Are internal transfers really free?',
                'answer' => 'Yes. Sending to another PoishaPay user is instant and fee-free because it settles on our internal ledger rather than on-chain.',
                'group' => 'Wallet',
                'sort_order' => 110,
            ],
            [
                'question' => 'What are the deposit and withdrawal fees?',
                'answer' => 'Deposits are free. Withdrawals carry a small percentage platform fee, shown before you confirm — PoishaPay absorbs the network gas cost.',
                'group' => 'Wallet',
                'sort_order' => 115,
            ],
            [
                'question' => 'How are my assets kept safe?',
                'answer' => 'Balances are held custodially, with the majority of crypto in offline cold storage and continuous reconciliation against on-chain holdings. Every balance is derived from an immutable double-entry ledger.',
                'group' => 'Wallet',
                'sort_order' => 120,
            ],
            [
                'question' => 'Can I see my balance in my own currency?',
                'answer' => 'Yes — choose a base currency and every asset is valued live in it, so you always know what your wallet is worth at a glance.',
                'group' => 'Wallet',
                'sort_order' => 125,
            ],

            // ── Cards ──
            [
                'question' => 'How do virtual cards work?',
                'answer' => 'Once your account is fully verified, you can issue a virtual card that draws from your balance. Use it for online payments anywhere the card network is accepted, and freeze or replace it instantly from the Cards screen.',
                'group' => 'Cards',
                'sort_order' => 200,
                'show_on_homepage' => true,
            ],
            [
                'question' => 'Is the card virtual or physical?',
                'answer' => 'You get a virtual card you can use online and add to your mobile wallet to tap to pay in-store immediately. A physical card can also be issued if enabled on your account.',
                'group' => 'Cards',
                'sort_order' => 205,
            ],
            [
                'question' => 'Which balance does the card spend from?',
                'answer' => 'It draws from your PoishaPay stablecoin balance and converts your asset to the merchant\'s currency at the point of sale, so you can spend crypto without pre-converting it.',
                'group' => 'Cards',
                'sort_order' => 210,
            ],
            [
                'question' => 'What does the card cost?',
                'answer' => 'There is no monthly or annual fee. A flat 1% is applied per transaction, and any one-time issuance price (if your operator sets one) is shown before you create the card.',
                'group' => 'Cards',
                'sort_order' => 215,
            ],
            [
                'question' => 'Can I freeze the card or set limits?',
                'answer' => 'Yes. Freeze and unfreeze instantly, set daily and per-transaction limits, restrict spending by country and block merchant categories — all from your dashboard, any time.',
                'group' => 'Cards',
                'sort_order' => 220,
            ],
            [
                'question' => 'Can I have more than one card?',
                'answer' => 'Yes — issue multiple virtual cards with their own limits and controls, for example one for subscriptions and one for day-to-day spending.',
                'group' => 'Cards',
                'sort_order' => 225,
            ],

            // ── Exchange ──
            [
                'question' => 'How is the exchange rate set?',
                'answer' => 'Rates come from live market data with a small, transparent spread that is always shown before you confirm. Some pairs carry their own spread, and it is always disclosed in the quote.',
                'group' => 'Exchange',
                'sort_order' => 300,
            ],
            [
                'question' => 'Will the exchange rate change after I see it?',
                'answer' => 'No. Each quote locks a fixed rate for a few seconds. As long as you confirm within that window, the amount you receive is exactly what was shown.',
                'group' => 'Exchange',
                'sort_order' => 305,
            ],
            [
                'question' => 'How fast are swaps?',
                'answer' => 'Instant. They settle to your PoishaPay balance the moment you confirm, on our internal ledger — there is no on-chain wait.',
                'group' => 'Exchange',
                'sort_order' => 310,
            ],
            [
                'question' => 'Can I swap between crypto and fiat?',
                'answer' => 'Yes, in either direction — stablecoin to fiat, fiat to crypto, or crypto to crypto — all from your wallet.',
                'group' => 'Exchange',
                'sort_order' => 315,
            ],
            [
                'question' => 'Are there limits on a swap?',
                'answer' => 'Some pairs have a minimum and maximum amount per swap, and larger conversions may require a completed KYC level. Any limit is shown when you build the quote.',
                'group' => 'Exchange',
                'sort_order' => 320,
            ],

            // ── Merchant Pay ──
            [
                'question' => 'What does it cost to accept payments?',
                'answer' => 'A flat 1% processing fee per payment. There is no setup cost, no monthly fee and no per-coin surcharge — the fee is shown on every invoice.',
                'group' => 'Merchant Pay',
                'sort_order' => 400,
            ],
            [
                'question' => 'How fast do I get paid as a merchant?',
                'answer' => 'Instantly. When a customer pays, the net amount settles straight to your PoishaPay wallet on our internal ledger — there is no multi-day payout or bank delay.',
                'group' => 'Merchant Pay',
                'sort_order' => 405,
            ],
            [
                'question' => 'Which coins can customers pay with?',
                'answer' => 'Stablecoins USDT and USDC plus major assets like BTC and ETH. The customer pays in whatever they hold and you receive your settlement asset.',
                'group' => 'Merchant Pay',
                'sort_order' => 410,
            ],
            [
                'question' => 'Are there chargebacks on crypto payments?',
                'answer' => 'No. Once a crypto payment settles it is final, so you are protected from the fraud reversals common with card payments.',
                'group' => 'Merchant Pay',
                'sort_order' => 415,
            ],
            [
                'question' => 'Can I refund a customer or automate checkout?',
                'answer' => 'Yes. Refund a paid invoice or cancel an unpaid one from your dashboard, and every paid invoice emits an invoice.paid webhook so you can automate fulfilment and reconciliation through the API.',
                'group' => 'Merchant Pay',
                'sort_order' => 420,
            ],

            // ── Selling on Shop ──
            [
                'question' => 'What does it cost to sell on Shop?',
                'answer' => 'Nothing to start — the Free plan has no monthly fee, one sales page and 8% commission per sale. Pro is $10/month for up to 3 products at 5% commission, and Business is $20/month for up to 10 products at 3% commission. There are no separate payment-processing fees.',
                'group' => 'Selling on Shop',
                'sort_order' => 500,
            ],
            [
                'question' => 'What can I sell?',
                'answer' => 'Seven product types: digital downloads, license keys, memberships, subscriptions, services, physical goods and bundles. Physical products support shipping and address capture at checkout.',
                'group' => 'Selling on Shop',
                'sort_order' => 505,
            ],
            [
                'question' => 'How and when do I get paid for sales?',
                'answer' => 'Payments settle straight to your PoishaPay balance — no external gateway. Earnings can be held for the buyer\'s refund window and then released automatically to your spendable balance, so refunds never leave you in the negative.',
                'group' => 'Selling on Shop',
                'sort_order' => 510,
            ],
            [
                'question' => 'How do buyers pay at checkout?',
                'answer' => 'Every checkout supports three rails: a PoishaPay wallet balance, a card, or crypto. Buyers pick whichever they have — you receive your settlement asset either way.',
                'group' => 'Selling on Shop',
                'sort_order' => 515,
            ],
            [
                'question' => 'Do I need to code to build a sales page?',
                'answer' => 'No. The page builder is fully visual — drag blocks, edit inline, preview and publish. Saved revisions let you roll back any change, and you can connect a custom domain for a branded storefront.',
                'group' => 'Selling on Shop',
                'sort_order' => 520,
            ],
            [
                'question' => 'How are refunds handled on Shop?',
                'answer' => 'You can approve refunds from your dashboard. A refund reverses the original ledger entries, revokes access to digital products and returns the buyer\'s money — fully audited end to end.',
                'group' => 'Selling on Shop',
                'sort_order' => 525,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'group' => $faq['group'],
                    'sort_order' => $faq['sort_order'],
                    'show_on_homepage' => $faq['show_on_homepage'] ?? false,
                    'status' => 'published',
                ],
            );
        }
    }
}
