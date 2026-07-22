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
