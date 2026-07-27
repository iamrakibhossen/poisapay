<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Marketing CMS pages (company/about/contact/careers/blog/api). Legal & policy
 * pages live in {@see LegalPagesSeeder}; help-center Q&A in {@see HelpFaqSeeder}.
 * Idempotent (updateOrCreate by slug) and faker-free — safe on staging/prod.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'about-us',
                'title' => 'About PaishaPay',
                'meta_description' => 'PaishaPay is a multi-chain wallet built for Bangladesh — hold, send and exchange crypto and Taka in one secure app.',
                'content' => <<<'HTML'
<p>PaishaPay is a multi-chain wallet built for Bangladesh. We let you hold crypto and Taka side by side, send money to anyone instantly, and spend your balance with virtual cards — all from one app.</p>
<h2>Our mission</h2>
<p>We believe moving money should be as easy as sending a message. PaishaPay brings together digital assets and everyday payments so that anyone, anywhere in Bangladesh, can participate in the global economy without friction.</p>
<h2>How we keep you safe</h2>
<p>Every account is protected by custodial cold storage, two-factor authentication, and KYC/AML checks. We never expose your private keys, and our operations team monitors the platform around the clock.</p>
<h2>Built for the community</h2>
<p>From instant peer-to-peer transfers with zero fees to a built-in exchange with transparent pricing, every feature is designed to serve the people who use it. We are just getting started.</p>
HTML,
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact Us',
                'meta_description' => 'Get in touch with the PaishaPay team — support, business and press enquiries.',
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
                'meta_description' => 'Join PaishaPay and help build the future of borderless payments.',
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
                'meta_description' => 'Product updates, security announcements and insights from the PaishaPay team.',
                'content' => <<<'HTML'
<p>The PaishaPay blog is where we share product updates, new features, and important security announcements.</p>
<p>We're preparing our first posts now. In the meantime, follow us on social media for the latest news, or check the <a href="/status">system status</a> page for live service updates.</p>
HTML,
            ],
            [
                'slug' => 'api',
                'title' => 'API Documentation',
                'meta_description' => 'Build on PaishaPay with our REST API and webhooks.',
                'content' => <<<'HTML'
<p>Build on PaishaPay with a clean REST API and webhooks — automate deposits, withdrawals, transfers and merchant invoicing directly from your own systems.</p>
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
    }
}
