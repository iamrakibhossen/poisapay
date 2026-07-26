<?php

declare(strict_types=1);

namespace App\Shop\Builder\Templates;

use App\Shop\Builder\DocumentSanitizer;

/**
 * Premium starter templates — each is a complete v2 block-tree document a seller can
 * apply to a page in one click. Templates only override the props that set the tone;
 * everything else falls back to block schema defaults, so they stay readable and edit
 * cleanly. Applying a template runs through {@see DocumentSanitizer}.
 */
final class TemplateLibrary
{
    /**
     * Metadata for the editor's template gallery (no documents — those are built on
     * demand in {@see self::document()}).
     *
     * @return list<array{id: string, name: string, category: string, description: string}>
     */
    public static function meta(): array
    {
        return array_map(
            static fn (array $t) => ['id' => $t['id'], 'name' => $t['name'], 'category' => $t['category'], 'description' => $t['description']],
            self::all(),
        );
    }

    /**
     * The built document for a template id, or null if unknown.
     *
     * @return array<string, mixed>|null
     */
    public static function document(string $id): ?array
    {
        foreach (self::all() as $t) {
            if ($t['id'] === $id) {
                return ($t['build'])();
            }
        }

        return null;
    }

    /**
     * @return list<array{id: string, name: string, category: string, description: string, build: callable(): array<string, mixed>}>
     */
    private static function all(): array
    {
        return [
            [
                'id' => 'saas', 'name' => 'SaaS', 'category' => 'Software',
                'description' => 'Product hero, logos, feature tabs, pricing and FAQ — the classic SaaS funnel.',
                'build' => fn () => Tpl::doc([
                    self::header('Start free'),
                    Tpl::n('hero', ['headline' => 'The all-in-one platform your team will actually use', 'tagline' => 'Ship faster, together', 'desc' => 'Plan, build and launch in one place — no more scattered tools.', 'btn' => 'Start free trial']),
                    Tpl::n('logos', ['heading' => 'Trusted by fast-growing teams']),
                    Tpl::n('feature-tabs', ['heading' => 'One workspace, everything you need']),
                    Tpl::n('features', ['eyebrow' => 'Features', 'heading' => 'Built for how you work']),
                    Tpl::n('pricing', ['heading' => 'Simple, transparent pricing']),
                    Tpl::n('testimonials', ['heading' => 'Teams love the switch']),
                    Tpl::n('faq'),
                    Tpl::n('cta-banner', ['heading' => 'Ready to move faster?', 'sub' => 'Start free — no credit card required.', 'btn' => 'Get started']),
                    self::footer(),
                ], Tpl::brand('#4f46e5')),
            ],
            [
                'id' => 'agency', 'name' => 'Agency', 'category' => 'Services',
                'description' => 'Bold hero, stats, case studies and a team section to win the pitch.',
                'build' => fn () => Tpl::doc([
                    self::header('Book a call'),
                    Tpl::n('hero', ['headline' => 'We design brands people remember', 'tagline' => 'Strategy · Design · Growth', 'desc' => 'A senior team that ships work that moves the needle.', 'btn' => 'Start a project']),
                    Tpl::n('stats'),
                    Tpl::n('benefits', ['eyebrow' => 'Why us', 'heading' => 'Outcomes, not just deliverables']),
                    Tpl::n('case-studies', ['heading' => 'Recent results']),
                    Tpl::n('testimonials', ['heading' => 'What clients say']),
                    Tpl::n('team', ['heading' => 'The people behind the work']),
                    Tpl::n('cta', ['heading' => 'Let’s build something great']),
                    self::footer(),
                ], Tpl::brand('#0f766e')),
            ],
            [
                'id' => 'crypto', 'name' => 'Crypto', 'category' => 'Web3',
                'description' => 'Announcement bar, metric stats and a trust-first layout for a token or app.',
                'build' => fn () => Tpl::doc([
                    Tpl::n('announcement-bar', ['text' => '🚀 Now live on mainnet', 'cta' => 'Read more']),
                    self::header('Launch app'),
                    Tpl::n('hero', ['headline' => 'The fastest way to move on-chain', 'tagline' => 'Non-custodial. Audited. Yours.', 'desc' => 'Swap, stake and earn with fees that make sense.', 'btn' => 'Launch app']),
                    Tpl::n('stats'),
                    Tpl::n('features', ['eyebrow' => 'Protocol', 'heading' => 'Built for safety and speed']),
                    Tpl::n('steps', ['heading' => 'Start earning in three steps']),
                    Tpl::n('faq'),
                    Tpl::n('cta-banner', ['heading' => 'Your keys. Your coins.', 'btn' => 'Launch app']),
                    self::footer(),
                ], Tpl::brand('#7c3aed')),
            ],
            [
                'id' => 'digital-product', 'name' => 'Digital Product', 'category' => 'Digital',
                'description' => 'Benefit-led page with value stack, guarantee and FAQ — for ebooks, templates, kits.',
                'build' => fn () => Tpl::doc([
                    self::header('Buy now'),
                    Tpl::n('hero', ['headline' => 'Everything you need to launch this weekend', 'tagline' => 'Instant download', 'desc' => 'A battle-tested kit that saves you 100+ hours.', 'btn' => 'Get instant access']),
                    Tpl::n('benefits', ['eyebrow' => 'What you get', 'heading' => 'Made to get you results']),
                    Tpl::n('bonuses', ['heading' => 'Everything included today']),
                    Tpl::n('testimonials', ['heading' => 'Loved by 2,000+ buyers']),
                    Tpl::n('guarantee'),
                    Tpl::n('faq'),
                    Tpl::n('cta', ['heading' => 'Get it today']),
                    self::footer(),
                ], Tpl::brand('#2563eb')),
            ],
            [
                'id' => 'course', 'name' => 'Course', 'category' => 'Education',
                'description' => 'Curriculum-style landing with outcomes, steps, bonuses and social proof.',
                'build' => fn () => Tpl::doc([
                    self::header('Enroll now'),
                    Tpl::n('hero', ['headline' => 'Go from beginner to confident in 30 days', 'tagline' => 'Self-paced · Lifetime access', 'desc' => 'A step-by-step path with real projects and feedback.', 'btn' => 'Enroll now']),
                    Tpl::n('benefits', ['eyebrow' => 'Outcomes', 'heading' => 'What you’ll be able to do']),
                    Tpl::n('steps', ['heading' => 'The learning path']),
                    Tpl::n('bonuses', ['heading' => 'Enroll today and also get']),
                    Tpl::n('testimonials', ['heading' => 'Student wins']),
                    Tpl::n('faq'),
                    Tpl::n('cta-banner', ['heading' => 'Start learning today', 'btn' => 'Enroll now']),
                    self::footer(),
                ], Tpl::brand('#d97706')),
            ],
            [
                'id' => 'creator', 'name' => 'Creator', 'category' => 'Personal',
                'description' => 'Personal-brand page with story, gallery and a newsletter capture.',
                'build' => fn () => Tpl::doc([
                    self::header('Subscribe'),
                    Tpl::n('hero', ['headline' => 'Hey, I’m your guide to doing this the smart way', 'tagline' => 'Creator · Writer · Maker', 'desc' => 'Join thousands learning with me every week.', 'btn' => 'Join the newsletter']),
                    Tpl::n('story', ['eyebrow' => 'My story', 'heading' => 'Why I do this']),
                    Tpl::n('gallery', ['heading' => 'Recent work']),
                    Tpl::n('testimonials', ['heading' => 'Kind words']),
                    Tpl::n('lead-capture', ['heading' => 'Get my weekly notes', 'sub' => 'One useful email a week. No spam.']),
                    self::footer(),
                ], Tpl::brand('#e11d48')),
            ],
            [
                'id' => 'affiliate', 'name' => 'Affiliate', 'category' => 'Marketing',
                'description' => 'Comparison-driven review page built to convert affiliate traffic.',
                'build' => fn () => Tpl::doc([
                    self::header('See the deal'),
                    Tpl::n('hero', ['headline' => 'The honest review you were looking for', 'tagline' => 'Tested for 30 days', 'desc' => 'Here’s exactly what’s good, what’s not, and who it’s for.', 'btn' => 'See today’s price']),
                    Tpl::n('comparison', ['heading' => 'How it stacks up']),
                    Tpl::n('before-after', ['heading' => 'The difference it made']),
                    Tpl::n('benefits', ['eyebrow' => 'The verdict', 'heading' => 'Why it’s worth it']),
                    Tpl::n('faq'),
                    Tpl::n('cta-banner', ['heading' => 'Get the best price today', 'btn' => 'See the deal']),
                    self::footer(),
                ], Tpl::brand('#059669')),
            ],
            [
                'id' => 'ecommerce', 'name' => 'E-commerce', 'category' => 'Retail',
                'description' => 'Product-first page with a shop grid, reviews and a guarantee.',
                'build' => fn () => Tpl::doc([
                    Tpl::n('announcement-bar', ['text' => 'Free shipping over $50', 'cta' => 'Shop now']),
                    self::header('Shop now'),
                    Tpl::n('hero', ['headline' => 'Designed to last. Made to love.', 'tagline' => 'New season', 'desc' => 'Premium essentials, honestly priced.', 'btn' => 'Shop the collection']),
                    Tpl::n('product-grid', ['heading' => 'Shop the collection']),
                    Tpl::n('testimonials', ['heading' => 'From happy customers']),
                    Tpl::n('trust-badges'),
                    Tpl::n('guarantee'),
                    Tpl::n('faq'),
                    self::footer(),
                ], Tpl::brand('#111827')),
            ],
            [
                'id' => 'local-business', 'name' => 'Local Business', 'category' => 'Local',
                'description' => 'Services, proof and a contact form — for a local storefront or trade.',
                'build' => fn () => Tpl::doc([
                    self::header('Get a quote'),
                    Tpl::n('hero', ['headline' => 'Your neighbourhood experts, here to help', 'tagline' => 'Trusted locally since day one', 'desc' => 'Fast, friendly and fairly priced service.', 'btn' => 'Get a free quote']),
                    Tpl::n('features', ['eyebrow' => 'Services', 'heading' => 'What we do']),
                    Tpl::n('stats'),
                    Tpl::n('testimonials', ['heading' => 'What locals say']),
                    Tpl::n('contact', ['heading' => 'Get in touch', 'sub' => 'Tell us what you need — we’ll reply fast.']),
                    self::footer(),
                ], Tpl::brand('#0d9488')),
            ],
            [
                'id' => 'startup', 'name' => 'Startup', 'category' => 'Software',
                'description' => 'Momentum-forward launch page with logos, roadmap and a big CTA.',
                'build' => fn () => Tpl::doc([
                    self::header('Join the beta'),
                    Tpl::n('hero', ['headline' => 'The future of your workflow, today', 'tagline' => 'Now in early access', 'desc' => 'Be among the first to try it — shape what we build next.', 'btn' => 'Join the beta']),
                    Tpl::n('logos', ['heading' => 'Backed by the best']),
                    Tpl::n('features', ['eyebrow' => 'Why now', 'heading' => 'A better way, finally']),
                    Tpl::n('stats'),
                    Tpl::n('timeline', ['heading' => 'What’s next']),
                    Tpl::n('cta-banner', ['heading' => 'Get early access', 'btn' => 'Join the beta']),
                    self::footer(),
                ], Tpl::brand('#4338ca')),
            ],
            [
                'id' => 'consultant', 'name' => 'Consultant', 'category' => 'Services',
                'description' => 'Authority-led page with a process, case studies and a lead form.',
                'build' => fn () => Tpl::doc([
                    self::header('Work with me'),
                    Tpl::n('hero', ['headline' => 'I help founders fix what’s slowing growth', 'tagline' => 'Advisory · Fractional · Workshops', 'desc' => 'Clear strategy and hands-on execution, minus the fluff.', 'btn' => 'Book an intro call']),
                    Tpl::n('benefits', ['eyebrow' => 'Engagements', 'heading' => 'How I can help']),
                    Tpl::n('steps', ['heading' => 'How we’ll work together']),
                    Tpl::n('case-studies', ['heading' => 'Selected results']),
                    Tpl::n('testimonials', ['heading' => 'What clients say']),
                    Tpl::n('lead-capture', ['heading' => 'Let’s talk', 'sub' => 'Drop your email and I’ll be in touch.']),
                    self::footer(),
                ], Tpl::brand('#0284c7')),
            ],
            [
                'id' => 'portfolio', 'name' => 'Portfolio', 'category' => 'Personal',
                'description' => 'Minimal, gallery-led showcase with an about story and contact.',
                'build' => fn () => Tpl::doc([
                    self::header('Contact'),
                    Tpl::n('hero', ['headline' => 'Selected work', 'tagline' => 'Designer & Developer', 'desc' => 'A small collection of things I’m proud of.', 'btn' => 'Get in touch']),
                    Tpl::n('gallery', ['heading' => 'Projects']),
                    Tpl::n('case-studies', ['heading' => 'Case studies']),
                    Tpl::n('story', ['eyebrow' => 'About', 'heading' => 'A bit about me']),
                    Tpl::n('contact', ['heading' => 'Work together?']),
                    self::footer(),
                ], Tpl::brand('#18181b')),
            ],
            [
                'id' => 'app-landing', 'name' => 'App Landing', 'category' => 'Software',
                'description' => 'Mobile-app page with feature tabs, ratings and a download CTA.',
                'build' => fn () => Tpl::doc([
                    self::header('Download'),
                    Tpl::n('hero', ['headline' => 'The app that keeps your life on track', 'tagline' => 'iOS & Android', 'desc' => 'Beautiful, fast and private by design.', 'btn' => 'Download free']),
                    Tpl::n('feature-tabs', ['heading' => 'Everything at your fingertips']),
                    Tpl::n('stats'),
                    Tpl::n('testimonials', ['heading' => 'Loved on the App Store']),
                    Tpl::n('faq'),
                    Tpl::n('cta-banner', ['heading' => 'Get the app today', 'btn' => 'Download free']),
                    self::footer(),
                ], Tpl::brand('#2563eb')),
            ],
            [
                'id' => 'coming-soon', 'name' => 'Coming Soon', 'category' => 'Launch',
                'description' => 'A focused pre-launch page: countdown, promise and email capture.',
                'build' => fn () => Tpl::doc([
                    Tpl::n('hero', ['headline' => 'Something great is on the way', 'tagline' => 'Launching soon', 'desc' => 'Be the first to know when we go live.', 'btn' => 'Notify me'], [], ['padTop' => 96]),
                    Tpl::n('countdown', ['label' => 'Launching in']),
                    Tpl::n('lead-capture', ['heading' => 'Get early access', 'sub' => 'Join the waitlist — we’ll email you at launch.']),
                    self::footer(),
                ], Tpl::brand('#6d28d9')),
            ],
            [
                'id' => 'lead-generation', 'name' => 'Lead Generation', 'category' => 'Marketing',
                'description' => 'A high-intent opt-in page: promise, proof, capture and FAQ.',
                'build' => fn () => Tpl::doc([
                    self::header('Get the guide'),
                    Tpl::n('hero', ['headline' => 'The free guide that saves you months of guesswork', 'tagline' => 'Free download', 'desc' => 'Everything we learned, distilled into one playbook.', 'btn' => 'Get the free guide']),
                    Tpl::n('benefits', ['eyebrow' => 'Inside', 'heading' => 'What you’ll learn']),
                    Tpl::n('lead-capture', ['heading' => 'Send me the guide', 'sub' => 'Enter your email and it’s yours, instantly.']),
                    Tpl::n('testimonials', ['heading' => 'Others found it useful']),
                    Tpl::n('faq'),
                    self::footer(),
                ], Tpl::brand('#16a34a')),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function header(string $cta): array
    {
        return Tpl::n('header', ['cta' => $cta]);
    }

    /** @return array<string, mixed> */
    private static function footer(): array
    {
        return Tpl::n('footer', []);
    }
}
