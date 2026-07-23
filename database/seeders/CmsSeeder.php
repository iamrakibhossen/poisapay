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
                'title' => 'About PoisaPay',
                'meta_description' => 'PoisaPay is a multi-chain wallet built for Bangladesh — hold, send and exchange crypto and Taka in one secure app.',
                'content' => <<<'HTML'
<p>PoisaPay is a multi-chain wallet built for Bangladesh. We let you hold crypto and Taka side by side, send money to anyone instantly, and spend your balance with virtual cards — all from one app.</p>
<h2>Our mission</h2>
<p>We believe moving money should be as easy as sending a message. PoisaPay brings together digital assets and everyday payments so that anyone, anywhere in Bangladesh, can participate in the global economy without friction.</p>
<h2>How we keep you safe</h2>
<p>Every account is protected by custodial cold storage, two-factor authentication, and KYC/AML checks. We never expose your private keys, and our operations team monitors the platform around the clock.</p>
<h2>Built for the community</h2>
<p>From instant peer-to-peer transfers with zero fees to a built-in exchange with transparent pricing, every feature is designed to serve the people who use it. We are just getting started.</p>
HTML,
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'meta_description' => 'The terms and conditions that govern your use of PoisaPay.',
                'content' => <<<'HTML'
<p>By creating an account and using PoisaPay, you agree to these Terms of Service. Please read them carefully.</p>
<h2>1. Eligibility</h2>
<p>You must be at least 18 years old and a resident of a jurisdiction where PoisaPay is available. You agree to provide accurate information and to complete identity verification when required.</p>
<h2>2. Your account</h2>
<p>You are responsible for keeping your login credentials confidential and for all activity that occurs under your account. Notify us immediately if you suspect unauthorised access.</p>
<h2>3. Acceptable use</h2>
<p>You may not use PoisaPay for money laundering, fraud, or any unlawful activity. We reserve the right to suspend or close accounts that violate these terms or applicable law.</p>
<h2>4. Fees and limits</h2>
<p>Fees and transaction limits are disclosed in the app before you confirm a transaction. We may adjust them from time to time and will notify you of material changes.</p>
<h2>5. Liability</h2>
<p>PoisaPay is provided on an "as is" basis. To the fullest extent permitted by law, we are not liable for losses arising from market movements, network delays, or events beyond our reasonable control.</p>
HTML,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta_description' => 'How PoisaPay collects, uses, and protects your personal information.',
                'content' => <<<'HTML'
<p>This Privacy Policy explains how PoisaPay collects, uses, and protects your personal information.</p>
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
                'meta_description' => 'PoisaPay legal centre — Terms of Service, Privacy Policy, AML & KYC Policy, and our compliance approach.',
                'content' => <<<'HTML'
<p>This page brings together the policies that govern your use of PoisaPay. Use the footer links to jump to a section. These policies may be updated from time to time; we will notify you of material changes through the app or by email.</p>

<h2 id="terms">Terms of Service</h2>
<p>By creating an account and using PoisaPay, you agree to these Terms of Service. Please read them carefully, together with the Privacy and AML &amp; KYC policies below.</p>
<h3>1. Eligibility</h3>
<p>You must be at least 18 years old and a resident of a jurisdiction where PoisaPay is available. You agree to provide accurate, current information and to complete identity verification when required.</p>
<h3>2. Your account</h3>
<p>You are responsible for keeping your login credentials confidential and for all activity that occurs under your account. Enable two-factor authentication and notify us immediately if you suspect unauthorised access.</p>
<h3>3. Acceptable use</h3>
<p>You may not use PoisaPay for money laundering, terrorist financing, fraud, sanctions evasion, or any unlawful activity. We may suspend, restrict, or close accounts that violate these terms or applicable law.</p>
<h3>4. Digital assets &amp; custody</h3>
<p>Crypto balances are held custodially on your behalf. Digital assets are volatile and their value can fall as well as rise. You are responsible for the tax consequences of your transactions in your jurisdiction.</p>
<h3>5. Fees and limits</h3>
<p>Fees and transaction limits are disclosed in the app before you confirm a transaction. We may adjust them from time to time and will give notice of material changes.</p>
<h3>6. Liability</h3>
<p>PoisaPay is provided on an "as is" basis. To the fullest extent permitted by law, we are not liable for losses arising from market movements, network delays, or events beyond our reasonable control.</p>

<h2 id="privacy">Privacy Policy</h2>
<p>This Privacy Policy explains how PoisaPay collects, uses, and protects your personal information.</p>
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
<p>PoisaPay is committed to preventing money laundering and the financing of illegal activity. We maintain a risk-based anti-money-laundering (AML) programme and cooperate with lawful requests from competent authorities.</p>
<h3>Know Your Customer</h3>
<p>Identity verification (KYC) is required to open and operate a PoisaPay account. We collect and verify identity information such as your name, date of birth, address, and government-issued identification. Higher limits and certain features may require additional verification tiers.</p>
<h3>Monitoring</h3>
<p>We monitor transactions for unusual or suspicious activity, apply risk-based limits, and screen against applicable sanctions and watchlists. We may request additional information or documentation, and file reports with regulators where required.</p>
<h3>Ongoing review</h3>
<p>We may periodically re-verify your information and request updated documents to keep your account in good standing.</p>

<h2 id="compliance">Compliance</h2>
<p>PoisaPay operates as a custodial platform and applies KYC/AML controls, transaction monitoring, and risk-based limits in line with applicable regulations in the jurisdictions we serve.</p>
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
                'meta_description' => 'Get in touch with the PoisaPay team — support, business and press enquiries.',
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
                'meta_description' => 'Join PoisaPay and help build the future of borderless payments.',
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
                'meta_description' => 'Product updates, security announcements and insights from the PoisaPay team.',
                'content' => <<<'HTML'
<p>The PoisaPay blog is where we share product updates, new features, and important security announcements.</p>
<p>We're preparing our first posts now. In the meantime, follow us on social media for the latest news, or check the <a href="/status">system status</a> page for live service updates.</p>
HTML,
            ],
            [
                'slug' => 'api',
                'title' => 'API Documentation',
                'meta_description' => 'Build on PoisaPay with our REST API and webhooks.',
                'content' => <<<'HTML'
<p>Build on PoisaPay with a clean REST API and webhooks — automate deposits, withdrawals, transfers and merchant invoicing directly from your own systems.</p>
<h2>What you can do</h2>
<p>Create invoices and payment links, query balances and transactions, and receive real-time webhook notifications for payments and settlements.</p>
<h2>Access</h2>
<p>The full public reference is on its way. For early access and API keys, contact <strong>business@poisapay.com</strong>.</p>
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

        $faqs = [
            [
                'question' => 'How do I make a deposit?',
                'answer' => 'Go to the Deposit screen, choose the asset and network you want to fund, and send crypto to the address shown. Once the network confirms your transaction, the balance is credited to your wallet automatically.',
                'group' => 'Deposits',
                'sort_order' => 10,
            ],
            [
                'question' => 'How long do withdrawals take?',
                'answer' => 'Withdrawal requests are reviewed and broadcast to the blockchain shortly after you confirm them. On-chain settlement depends on network congestion, but most withdrawals complete within a few minutes.',
                'group' => 'Withdrawals',
                'sort_order' => 20,
            ],
            [
                'question' => 'Why do I need to verify my identity (KYC)?',
                'answer' => 'Identity verification protects you and the platform from fraud and helps us meet anti-money-laundering rules. Some features, such as virtual cards and higher limits, are unlocked only after your verification is approved.',
                'group' => 'Verification',
                'sort_order' => 30,
            ],
            [
                'question' => 'How do virtual cards work?',
                'answer' => 'Once your account is fully verified, you can issue a virtual card that draws from your balance. Use it for online payments anywhere the card network is accepted, and freeze or replace it instantly from the Cards screen.',
                'group' => 'Cards',
                'sort_order' => 40,
            ],
            [
                'question' => 'What fees does PoisaPay charge?',
                'answer' => 'Peer-to-peer transfers between PoisaPay users are free. Deposits, withdrawals, and exchanges may carry a small network or spread fee, which is always shown transparently before you confirm the transaction.',
                'group' => 'Fees',
                'sort_order' => 50,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'group' => $faq['group'],
                    'sort_order' => $faq['sort_order'],
                    'show_on_homepage' => true,
                    'status' => 'published',
                ],
            );
        }
    }
}
