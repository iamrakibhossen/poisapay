<?php

declare(strict_types=1);

namespace App\Shop\Builder\Templates;

use App\Shop\Builder\DocumentSanitizer;

/**
 * Premium starter templates — each is a complete v2 block-tree document a seller can
 * apply to a page in one click. Templates only override the props that set the tone
 * (incl. the section **variant** + dark mode); everything else falls back to block
 * schema defaults, so they stay readable and edit cleanly. Applying a template runs
 * through {@see DocumentSanitizer}.
 *
 * Design rule: the hero uses image-free variants (centered / gradient / minimal) so
 * the gallery preview looks finished rather than showing an "add image" placeholder.
 * Variety is expressed through the other sections' variants + dark themes.
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
                'description' => 'Centered hero, logos, feature tabs, pricing with a monthly/yearly toggle and FAQ.',
                'build' => fn () => Tpl::doc([
                    self::header('Start free'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'Now with AI', 'headline' => 'The all-in-one platform your team will actually use', 'tagline' => 'Ship faster, together', 'desc' => 'Plan, build and launch in one place — no more scattered tools.', 'btn' => 'Start free trial']),
                    Tpl::n('logos', ['heading' => 'Trusted by fast-growing teams']),
                    Tpl::n('feature-tabs', ['heading' => 'One workspace, everything you need']),
                    Tpl::n('features', ['variant' => 'iconTop', 'cols' => 3, 'eyebrow' => 'Features', 'heading' => 'Built for how you work', 'sub' => 'Everything your team needs to move from idea to launch.']),
                    Tpl::n('pricing', ['variant' => 'cards', 'cols' => 3, 'billingToggle' => true, 'yearlyNote' => 'Save 20% billed yearly', 'heading' => 'Simple, transparent pricing', 'sub' => 'Start free. Upgrade when you grow.']),
                    Tpl::n('testimonials', ['variant' => 'cards', 'cols' => 3, 'heading' => 'Teams love the switch']),
                    Tpl::n('faq', ['variant' => 'split']),
                    Tpl::n('cta-banner', ['variant' => 'gradient', 'heading' => 'Ready to move faster?', 'sub' => 'Start free — no credit card required.', 'btn' => 'Get started']),
                    self::footer(),
                ], Tpl::brand('#4f46e5')),
            ],
            [
                'id' => 'agency', 'name' => 'Agency', 'category' => 'Services',
                'description' => 'Bold hero, stats, alternating benefits, case studies and a big split CTA.',
                'build' => fn () => Tpl::doc([
                    self::header('Book a call'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'Strategy · Design · Growth', 'headline' => 'We design brands people remember', 'tagline' => 'Work that moves the needle', 'desc' => 'A senior team that ships outcomes, not just deliverables.', 'btn' => 'Start a project']),
                    Tpl::n('stats'),
                    Tpl::n('benefits', ['eyebrow' => 'Why us', 'heading' => 'Outcomes, not just deliverables']),
                    Tpl::n('case-studies', ['heading' => 'Recent results']),
                    Tpl::n('testimonials', ['variant' => 'single', 'heading' => 'What clients say']),
                    Tpl::n('team', ['heading' => 'The people behind the work']),
                    Tpl::n('cta-banner', ['variant' => 'split', 'heading' => 'Let’s build something great', 'sub' => 'Tell us about your project — we reply within a day.', 'btn' => 'Book a call']),
                    self::footer(),
                ], Tpl::brand('#0f766e')),
            ],
            [
                'id' => 'crypto', 'name' => 'Crypto', 'category' => 'Web3',
                'description' => 'Dark, gradient hero with metric stats and a trust-first Web3 layout.',
                'build' => fn () => Tpl::doc([
                    Tpl::n('announcement-bar', ['text' => '🚀 Now live on mainnet', 'cta' => 'Read more']),
                    self::header('Launch app', ['transparent' => true]),
                    Tpl::n('hero', ['variant' => 'gradient', 'eyebrow' => 'Non-custodial', 'headline' => 'The fastest way to move on-chain', 'tagline' => 'Audited. Yours.', 'desc' => 'Swap, stake and earn with fees that make sense.', 'btn' => 'Launch app']),
                    Tpl::n('stats', ['dark' => true]),
                    Tpl::n('features', ['variant' => 'iconLeft', 'cols' => 2, 'dark' => true, 'eyebrow' => 'Protocol', 'heading' => 'Built for safety and speed']),
                    Tpl::n('steps', ['heading' => 'Start earning in three steps']),
                    Tpl::n('faq', ['variant' => 'accordion', 'dark' => true]),
                    Tpl::n('cta-banner', ['variant' => 'dark', 'heading' => 'Your keys. Your coins.', 'sub' => 'Self-custody from the first tap.', 'btn' => 'Launch app']),
                    self::footer(['darkMode' => true]),
                ], Tpl::brand('#7c3aed')),
            ],
            [
                'id' => 'digital-product', 'name' => 'Digital Product', 'category' => 'Digital',
                'description' => 'Benefit-led page with a value stack, carousel proof and guarantee.',
                'build' => fn () => Tpl::doc([
                    self::header('Buy now'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'Instant download', 'headline' => 'Everything you need to launch this weekend', 'tagline' => 'Save 100+ hours', 'desc' => 'A battle-tested kit that pays for itself on day one.', 'btn' => 'Get instant access']),
                    Tpl::n('benefits', ['eyebrow' => 'What you get', 'heading' => 'Made to get you results']),
                    Tpl::n('bonuses', ['heading' => 'Everything included today']),
                    Tpl::n('testimonials', ['variant' => 'carousel', 'heading' => 'Loved by 2,000+ buyers']),
                    Tpl::n('guarantee'),
                    Tpl::n('faq', ['variant' => 'accordion']),
                    Tpl::n('cta-banner', ['variant' => 'simple', 'heading' => 'Get it today', 'sub' => 'Instant access, lifetime updates.', 'btn' => 'Buy now']),
                    self::footer(),
                ], Tpl::brand('#2563eb')),
            ],
            [
                'id' => 'course', 'name' => 'Course', 'category' => 'Education',
                'description' => 'Curriculum-style landing with alternating outcomes, steps and proof.',
                'build' => fn () => Tpl::doc([
                    self::header('Enroll now'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'Self-paced · Lifetime access', 'headline' => 'Go from beginner to confident in 30 days', 'tagline' => 'Real projects, real feedback', 'desc' => 'A step-by-step path you can actually finish.', 'btn' => 'Enroll now']),
                    Tpl::n('benefits', ['eyebrow' => 'Outcomes', 'heading' => 'What you’ll be able to do']),
                    Tpl::n('steps', ['heading' => 'The learning path']),
                    Tpl::n('bonuses', ['heading' => 'Enroll today and also get']),
                    Tpl::n('testimonials', ['variant' => 'cards', 'cols' => 3, 'heading' => 'Student wins']),
                    Tpl::n('faq'),
                    Tpl::n('cta-banner', ['variant' => 'card', 'heading' => 'Start learning today', 'sub' => 'Join thousands already enrolled.', 'btn' => 'Enroll now']),
                    self::footer(),
                ], Tpl::brand('#d97706')),
            ],
            [
                'id' => 'creator', 'name' => 'Creator', 'category' => 'Personal',
                'description' => 'Minimal personal-brand page with story, gallery and newsletter capture.',
                'build' => fn () => Tpl::doc([
                    self::header('Subscribe'),
                    Tpl::n('hero', ['variant' => 'minimal', 'headline' => 'Hey, I’m your guide to doing this the smart way', 'desc' => 'Join thousands learning with me every week.', 'btn' => 'Join the newsletter']),
                    Tpl::n('story', ['eyebrow' => 'My story', 'heading' => 'Why I do this']),
                    Tpl::n('gallery', ['heading' => 'Recent work', 'layout' => 'masonry', 'lightbox' => true]),
                    Tpl::n('testimonials', ['variant' => 'minimal', 'cols' => 2, 'heading' => 'Kind words']),
                    Tpl::n('lead-capture', ['heading' => 'Get my weekly notes', 'sub' => 'One useful email a week. No spam.']),
                    self::footer(),
                ], Tpl::brand('#e11d48')),
            ],
            [
                'id' => 'affiliate', 'name' => 'Affiliate', 'category' => 'Marketing',
                'description' => 'Comparison-driven review page built to convert affiliate traffic.',
                'build' => fn () => Tpl::doc([
                    self::header('See the deal'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'Tested for 30 days', 'headline' => 'The honest review you were looking for', 'tagline' => 'No fluff, just findings', 'desc' => 'Exactly what’s good, what’s not, and who it’s for.', 'btn' => 'See today’s price']),
                    Tpl::n('comparison', ['heading' => 'How it stacks up']),
                    Tpl::n('before-after', ['heading' => 'The difference it made']),
                    Tpl::n('benefits', ['eyebrow' => 'The verdict', 'heading' => 'Why it’s worth it']),
                    Tpl::n('faq', ['variant' => 'cards']),
                    Tpl::n('cta-banner', ['variant' => 'simple', 'heading' => 'Get the best price today', 'btn' => 'See the deal']),
                    self::footer(),
                ], Tpl::brand('#059669')),
            ],
            [
                'id' => 'ecommerce', 'name' => 'E-commerce', 'category' => 'Retail',
                'description' => 'Product-first page with a shop grid, carousel reviews and a dark CTA.',
                'build' => fn () => Tpl::doc([
                    Tpl::n('announcement-bar', ['text' => 'Free shipping over $50', 'cta' => 'Shop now']),
                    self::header('Shop now'),
                    Tpl::n('hero', ['variant' => 'minimal', 'eyebrow' => 'New season', 'headline' => 'Designed to last. Made to love.', 'desc' => 'Premium essentials, honestly priced.', 'btn' => 'Shop the collection']),
                    Tpl::n('product-grid', ['heading' => 'Shop the collection', 'sub' => 'Handpicked, made to last.']),
                    Tpl::n('testimonials', ['variant' => 'carousel', 'heading' => 'From happy customers']),
                    Tpl::n('trust-badges'),
                    Tpl::n('guarantee'),
                    Tpl::n('faq'),
                    Tpl::n('cta-banner', ['variant' => 'dark', 'heading' => 'Join 20,000+ happy customers', 'sub' => 'Free shipping, easy returns.', 'btn' => 'Shop now']),
                    self::footer(),
                ], Tpl::brand('#111827')),
            ],
            [
                'id' => 'local-business', 'name' => 'Local Business', 'category' => 'Local',
                'description' => 'Services, proof and a contact form — for a local storefront or trade.',
                'build' => fn () => Tpl::doc([
                    self::header('Get a quote'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'Trusted locally', 'headline' => 'Your neighbourhood experts, here to help', 'tagline' => 'Fast, friendly, fair', 'desc' => 'Quality work with a smile — and a fair price.', 'btn' => 'Get a free quote']),
                    Tpl::n('features', ['variant' => 'iconLeft', 'cols' => 2, 'eyebrow' => 'Services', 'heading' => 'What we do']),
                    Tpl::n('stats'),
                    Tpl::n('testimonials', ['variant' => 'cards', 'cols' => 3, 'heading' => 'What locals say']),
                    Tpl::n('contact', ['heading' => 'Get in touch', 'sub' => 'Tell us what you need — we’ll reply fast.']),
                    self::footer(),
                ], Tpl::brand('#0d9488')),
            ],
            [
                'id' => 'startup', 'name' => 'Startup', 'category' => 'Software',
                'description' => 'Momentum-forward launch page with a gradient hero, logos and roadmap.',
                'build' => fn () => Tpl::doc([
                    self::header('Join the beta', ['transparent' => true]),
                    Tpl::n('hero', ['variant' => 'gradient', 'eyebrow' => 'Now in early access', 'headline' => 'The future of your workflow, today', 'tagline' => 'Shape what we build next', 'desc' => 'Be among the first — help define the product.', 'btn' => 'Join the beta']),
                    Tpl::n('logos', ['heading' => 'Backed by the best']),
                    Tpl::n('features', ['variant' => 'iconTop', 'cols' => 3, 'eyebrow' => 'Why now', 'heading' => 'A better way, finally']),
                    Tpl::n('stats'),
                    Tpl::n('timeline', ['heading' => 'What’s next']),
                    Tpl::n('cta-banner', ['variant' => 'gradient', 'heading' => 'Get early access', 'sub' => 'Free while in beta.', 'btn' => 'Join the beta']),
                    self::footer(),
                ], Tpl::brand('#4338ca')),
            ],
            [
                'id' => 'consultant', 'name' => 'Consultant', 'category' => 'Services',
                'description' => 'Authority-led page with a process, case studies and a lead form.',
                'build' => fn () => Tpl::doc([
                    self::header('Work with me'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'Advisory · Fractional · Workshops', 'headline' => 'I help founders fix what’s slowing growth', 'tagline' => 'Clarity, then execution', 'desc' => 'Hands-on strategy, minus the fluff.', 'btn' => 'Book an intro call']),
                    Tpl::n('benefits', ['eyebrow' => 'Engagements', 'heading' => 'How I can help']),
                    Tpl::n('steps', ['heading' => 'How we’ll work together']),
                    Tpl::n('case-studies', ['heading' => 'Selected results']),
                    Tpl::n('testimonials', ['variant' => 'single', 'heading' => 'What clients say']),
                    Tpl::n('lead-capture', ['heading' => 'Let’s talk', 'sub' => 'Drop your email and I’ll be in touch.']),
                    self::footer(),
                ], Tpl::brand('#0284c7')),
            ],
            [
                'id' => 'portfolio', 'name' => 'Portfolio', 'category' => 'Personal',
                'description' => 'Minimal dark showcase with a masonry gallery, about story and contact.',
                'build' => fn () => Tpl::doc([
                    self::header('Contact', ['transparent' => true]),
                    Tpl::n('hero', ['variant' => 'minimal', 'dark' => true, 'eyebrow' => 'Designer & Developer', 'headline' => 'Selected work', 'desc' => 'A small collection of things I’m proud of.', 'btn' => 'Get in touch']),
                    Tpl::n('gallery', ['heading' => 'Projects', 'layout' => 'masonry', 'lightbox' => true, 'filter' => true]),
                    Tpl::n('case-studies', ['heading' => 'Case studies']),
                    Tpl::n('story', ['eyebrow' => 'About', 'heading' => 'A bit about me']),
                    Tpl::n('contact', ['heading' => 'Work together?']),
                    self::footer(['darkMode' => true]),
                ], Tpl::brand('#18181b')),
            ],
            [
                'id' => 'app-landing', 'name' => 'App Landing', 'category' => 'Software',
                'description' => 'Mobile-app page with feature tabs, carousel ratings and a card CTA.',
                'build' => fn () => Tpl::doc([
                    self::header('Download'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'iOS & Android', 'headline' => 'The app that keeps your life on track', 'tagline' => 'Private by design', 'desc' => 'Beautiful, fast, and yours alone.', 'btn' => 'Download free']),
                    Tpl::n('feature-tabs', ['heading' => 'Everything at your fingertips']),
                    Tpl::n('stats'),
                    Tpl::n('testimonials', ['variant' => 'carousel', 'heading' => 'Loved on the App Store']),
                    Tpl::n('faq', ['variant' => 'cards']),
                    Tpl::n('cta-banner', ['variant' => 'card', 'heading' => 'Get the app today', 'sub' => 'Free on iOS & Android.', 'btn' => 'Download free']),
                    self::footer(),
                ], Tpl::brand('#2563eb')),
            ],
            [
                'id' => 'coming-soon', 'name' => 'Coming Soon', 'category' => 'Launch',
                'description' => 'A focused, dark pre-launch page: gradient promise, countdown and capture.',
                'build' => fn () => Tpl::doc([
                    Tpl::n('hero', ['variant' => 'gradient', 'eyebrow' => 'Launching soon', 'headline' => 'Something great is on the way', 'tagline' => 'Be the first to know', 'desc' => 'Join the list and we’ll email you the moment we go live.', 'btn' => 'Notify me', 'showRating' => false, 'showTrust' => false]),
                    Tpl::n('countdown', ['label' => 'Launching in']),
                    Tpl::n('lead-capture', ['heading' => 'Get early access', 'sub' => 'Join the waitlist — we’ll email you at launch.']),
                    self::footer(['darkMode' => true]),
                ], Tpl::brand('#6d28d9')),
            ],
            [
                'id' => 'lead-generation', 'name' => 'Lead Generation', 'category' => 'Marketing',
                'description' => 'A high-intent opt-in page: promise, proof, capture and FAQ.',
                'build' => fn () => Tpl::doc([
                    self::header('Get the guide'),
                    Tpl::n('hero', ['variant' => 'centered', 'eyebrow' => 'Free download', 'headline' => 'The free guide that saves you months of guesswork', 'tagline' => 'Everything, distilled', 'desc' => 'One playbook with everything we learned the hard way.', 'btn' => 'Get the free guide']),
                    Tpl::n('features', ['variant' => 'iconTop', 'cols' => 3, 'eyebrow' => 'Inside', 'heading' => 'What you’ll learn']),
                    Tpl::n('lead-capture', ['heading' => 'Send me the guide', 'sub' => 'Enter your email and it’s yours, instantly.']),
                    Tpl::n('testimonials', ['variant' => 'cards', 'cols' => 3, 'heading' => 'Others found it useful']),
                    Tpl::n('faq'),
                    self::footer(),
                ], Tpl::brand('#16a34a')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function header(string $cta, array $extra = []): array
    {
        return Tpl::n('header', array_merge(['cta' => $cta], $extra));
    }

    /**
     * A finished footer with brand social icons + quick links, so templates don't
     * ship with an empty footer.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function footer(array $extra = []): array
    {
        return Tpl::n('footer', array_merge([
            'tagline' => 'Made with care.',
            'links' => [
                ['label' => 'Features', 'url' => '#features'],
                ['label' => 'Pricing', 'url' => '#pricing'],
                ['label' => 'FAQ', 'url' => '#faq'],
                ['label' => 'Contact', 'url' => '#contact'],
            ],
            'socialLinks' => [
                ['platform' => 'x', 'url' => 'https://x.com'],
                ['platform' => 'instagram', 'url' => 'https://instagram.com'],
                ['platform' => 'linkedin', 'url' => 'https://linkedin.com'],
                ['platform' => 'youtube', 'url' => 'https://youtube.com'],
            ],
        ], $extra));
    }
}
