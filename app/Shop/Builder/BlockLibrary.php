<?php

declare(strict_types=1);

namespace App\Shop\Builder;

use App\Shop\Builder\Blocks\ConfigBlock;
use App\Shop\ShopServiceProvider;

/**
 * The declarative catalogue of every built-in block. This is the ONE place a block
 * is introduced: add an entry here + a Blade partial at
 * resources/views/builder/blocks/{type}.blade.php — no other file changes.
 *
 * {@see ShopServiceProvider} feeds these into the {@see BlockRegistry}.
 */
final class BlockLibrary
{
    /**
     * @return list<ConfigBlock>
     */
    public static function all(): array
    {
        return [
            // ─── Layout ────────────────────────────────────────────────────────
            new ConfigBlock('section', 'Section', 'square-2-stack', BlockCategory::Layout, container: true, schema: [
                'style' => [
                    Field::select('width', 'Content width', ['narrow' => 'Narrow', 'medium' => 'Medium', 'wide' => 'Wide', 'full' => 'Full'], 'medium'),
                ],
            ], allowedChildren: []),
            new ConfigBlock('row', 'Row', 'view-columns', BlockCategory::Layout, container: true, schema: [
                'style' => [
                    Field::select('align', 'Vertical align', ['top' => 'Top', 'center' => 'Center', 'bottom' => 'Bottom'], 'top'),
                ],
            ], allowedChildren: ['column']),
            new ConfigBlock('column', 'Column', 'stop', BlockCategory::Layout, container: true, schema: [
                'style' => [
                    Field::select('width', 'Width', ['auto' => 'Auto', '1/2' => 'Half', '1/3' => 'Third', '2/3' => 'Two thirds', '1/4' => 'Quarter', 'full' => 'Full'], 'auto'),
                ],
            ]),
            new ConfigBlock('spacer', 'Spacer', 'arrows-up-down', BlockCategory::Layout, schema: [
                'content' => [Field::number('height', 'Height (px)', 48, 0, 400)],
            ]),
            new ConfigBlock('divider', 'Divider', 'minus', BlockCategory::Layout, schema: [
                'style' => [Field::color('color', 'Colour', '#e2e8f0')],
            ]),
            new ConfigBlock('header', 'Header / Nav', 'bars-3', BlockCategory::Layout, schema: [
                'content' => [
                    Field::select('preset', 'Layout', ['left' => 'Brand left', 'center' => 'Brand centered', 'minimal' => 'Minimal'], 'left'),
                    Field::text('brand', 'Brand name', ''), // blank → the store name
                    Field::toggle('showLogo', 'Show logo mark', true),
                    Field::image('logo', 'Logo image', ''), // blank → store logo, then initials
                    Field::repeater('links', 'Menu links', [Field::text('label', 'Label', ''), Field::link('href', 'URL', '#')], [
                        ['label' => 'Features', 'href' => '#features'],
                        ['label' => 'Pricing', 'href' => '#pricing'],
                        ['label' => 'FAQ', 'href' => '#faq'],
                    ]),
                    Field::repeater('socialLinks', 'Social icons', [self::socialField(), Field::link('url', 'URL', '#')], []),
                    Field::text('cta', 'Button label', 'Buy now'),
                    Field::text('secondaryLabel', 'Secondary link', ''),
                    Field::link('secondaryHref', 'Secondary URL', '#'),
                    Field::toggle('sticky', 'Stick to top', true),
                    Field::toggle('transparent', 'Transparent (overlay hero)', false),
                    Field::number('height', 'Height (px)', 64, 44, 120),
                ],
            ]),
            new ConfigBlock('footer', 'Footer', 'building-storefront', BlockCategory::Layout, schema: [
                'content' => [
                    Field::text('brandName', 'Brand name', ''), // blank → the store name
                    Field::image('logo', 'Logo image', ''), // blank → store logo, then initials
                    Field::textarea('tagline', 'Tagline', 'Everything you need, in one place.'),
                    Field::repeater('links', 'Links', [Field::text('label', 'Label', ''), Field::link('url', 'URL', '#')], []),
                    Field::repeater('socialLinks', 'Social icons', [self::socialField(), Field::link('url', 'URL', '#')], []),
                    Field::text('copyright', 'Copyright', ''), // blank → © year brand
                    Field::toggle('darkMode', 'Dark footer', false),
                ],
            ]),

            // ─── Content ───────────────────────────────────────────────────────
            new ConfigBlock('heading', 'Heading', 'h1', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('text', 'Text', 'Your headline here'),
                    Field::select('level', 'Level', ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'], 'h2'),
                    Field::select('align', 'Align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'center'),
                ],
            ]),
            new ConfigBlock('text', 'Text', 'bars-3-bottom-left', BlockCategory::Content, schema: [
                'content' => [
                    Field::richtext('html', 'Text', 'Write something persuasive.'),
                    Field::select('align', 'Align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'left'),
                ],
            ]),
            new ConfigBlock('button', 'Button', 'cursor-arrow-rays', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('label', 'Label', 'Learn more'),
                    Field::select('action', 'Action', ['buy' => 'Start checkout', 'link' => 'Open link'], 'buy'),
                    Field::link('href', 'Link URL', '#'),
                    Field::select('align', 'Align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'center'),
                ],
            ]),
            new ConfigBlock('features', 'Feature grid', 'squares-2x2', BlockCategory::Content, schema: [
                'content' => [
                    Field::variant([
                        'cards' => 'Cards', 'iconTop' => 'Icon top', 'iconLeft' => 'Icon left', 'alternating' => 'Alternating',
                    ], 'cards'),
                    Field::text('eyebrow', 'Eyebrow', 'Features'),
                    Field::text('heading', 'Heading', 'Everything you get'),
                    Field::text('sub', 'Subheading', ''),
                    Field::number('cols', 'Columns', 2, 2, 4),
                    Field::repeater('items', 'Features', [
                        Field::text('title', 'Title', ''),
                        Field::textarea('desc', 'Description', ''),
                        self::iconField(),
                    ], [
                        ['title' => 'Fast setup', 'desc' => 'Go live in minutes, not weeks.', 'icon' => 'bolt'],
                        ['title' => 'Beautiful UI', 'desc' => 'Polished, responsive design out of the box.', 'icon' => 'sparkles'],
                        ['title' => 'Great support', 'desc' => 'Real humans, fast replies.', 'icon' => 'chat-bubble-left-right'],
                        ['title' => 'Lifetime updates', 'desc' => 'Buy once, get every future update.', 'icon' => 'arrow-path'],
                    ]),
                    Field::toggle('dark', 'Dark background', false),
                ],
            ]),
            new ConfigBlock('benefits', 'Benefits', 'check-badge', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('eyebrow', 'Eyebrow', 'Benefits'),
                    Field::text('heading', 'Heading', 'Why you’ll love it'),
                    Field::text('subheading', 'Subheading', 'The outcomes you get from day one.'),
                    Field::repeater('items', 'Benefits', [Field::text('title', 'Title', ''), Field::textarea('desc', 'Description', '')], [
                        ['title' => 'Save 100+ hours', 'desc' => 'Skip the boilerplate and ship faster.'],
                        ['title' => 'Clean, tested code', 'desc' => 'Production-grade from day one.'],
                    ]),
                ],
            ]),
            new ConfigBlock('steps', 'How it works', 'list-bullet', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Up and running in minutes'),
                    Field::repeater('items', 'Steps', [Field::text('title', 'Title', ''), Field::textarea('desc', 'Description', '')], [
                        ['title' => 'Buy in seconds', 'desc' => 'Checkout with your wallet, card or crypto.'],
                        ['title' => 'Get instant access', 'desc' => 'Your files or membership arrive right away.'],
                        ['title' => 'Start winning', 'desc' => 'Put it to work and see results fast.'],
                    ]),
                ],
            ]),
            new ConfigBlock('bonuses', 'What’s included', 'gift', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Everything you get today'),
                    Field::repeater('items', 'Items', [Field::text('title', 'Title', ''), Field::text('value', 'Value', '')], [
                        ['title' => 'Quick-start guide', 'value' => '$29'],
                        ['title' => 'Private community access', 'value' => '$99'],
                    ]),
                ],
            ]),
            new ConfigBlock('faq', 'FAQ', 'question-mark-circle', BlockCategory::Content, schema: [
                'content' => [
                    Field::variant(['accordion' => 'Accordion', 'cards' => 'Cards', 'split' => 'Two-column'], 'accordion'),
                    Field::text('eyebrow', 'Eyebrow', 'FAQ'),
                    Field::text('heading', 'Heading', 'Questions & answers'),
                    Field::text('sub', 'Subheading', ''),
                    Field::repeater('items', 'Questions', [Field::text('q', 'Question', ''), Field::textarea('a', 'Answer', '')], [
                        ['q' => 'What do I get?', 'a' => 'Instant access to everything listed, right after payment.'],
                        ['q' => 'Refund policy?', 'a' => '14-day money-back guarantee, no questions asked.'],
                    ]),
                    Field::toggle('dark', 'Dark background', false),
                ],
            ]),

            new ConfigBlock('icon-list', 'Checklist', 'check-circle', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'What you can do'),
                    Field::repeater('items', 'Items', [Field::text('text', 'Text', '')], [
                        ['text' => 'Launch in minutes'], ['text' => 'No code required'], ['text' => 'Cancel anytime'], ['text' => 'Priority support'],
                    ]),
                ],
            ]),
            new ConfigBlock('feature-tabs', 'Feature tabs', 'rectangle-group', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Explore the features'),
                    Field::repeater('items', 'Tabs', [Field::text('title', 'Tab title', ''), Field::textarea('body', 'Body', ''), Field::image('image', 'Image URL', '')], [
                        ['title' => 'Dashboard', 'body' => 'A clean overview of everything that matters.'],
                        ['title' => 'Automation', 'body' => 'Set it once and let it run on autopilot.'],
                    ]),
                ],
            ]),
            new ConfigBlock('accordion', 'Accordion', 'queue-list', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Details'),
                    Field::repeater('items', 'Rows', [Field::text('title', 'Title', ''), Field::textarea('body', 'Body', '')], [
                        ['title' => 'What’s included', 'body' => 'Everything you need to get started today.'],
                        ['title' => 'How delivery works', 'body' => 'Instant access right after checkout.'],
                    ]),
                ],
            ]),
            new ConfigBlock('comparison', 'Comparison table', 'table-cells', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'How we compare'),
                    Field::text('usLabel', 'Your column', 'Us'),
                    Field::text('themLabel', 'Their column', 'Others'),
                    Field::repeater('items', 'Rows', [Field::text('label', 'Feature', ''), Field::toggle('us', 'You have it', true), Field::toggle('them', 'They have it', false)], [
                        ['label' => 'Instant delivery', 'us' => true, 'them' => false],
                        ['label' => 'Lifetime updates', 'us' => true, 'them' => false],
                        ['label' => 'Money-back guarantee', 'us' => true, 'them' => true],
                    ]),
                ],
            ]),
            new ConfigBlock('before-after', 'Before / After', 'arrows-right-left', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'The transformation'),
                    Field::text('beforeLabel', 'Before label', 'Before'),
                    Field::text('afterLabel', 'After label', 'After'),
                    Field::textarea('before', 'Before (one per line)', "Endless manual work\nMissed deadlines\nConstant guesswork"),
                    Field::textarea('after', 'After (one per line)', "Automated in minutes\nAlways on time\nData-driven decisions"),
                ],
            ]),
            new ConfigBlock('timeline', 'Timeline', 'flag', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'The roadmap'),
                    Field::repeater('items', 'Milestones', [Field::text('date', 'Date / label', ''), Field::text('title', 'Title', ''), Field::textarea('desc', 'Description', '')], [
                        ['date' => 'Step 1', 'title' => 'Sign up', 'desc' => 'Create your account in seconds.'],
                        ['date' => 'Step 2', 'title' => 'Set it up', 'desc' => 'Follow the guided onboarding.'],
                        ['date' => 'Step 3', 'title' => 'Grow', 'desc' => 'Watch the results roll in.'],
                    ]),
                ],
            ]),
            new ConfigBlock('progress', 'Progress bars', 'chart-bar-square', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'By the numbers'),
                    Field::repeater('items', 'Bars', [Field::text('label', 'Label', ''), Field::number('value', 'Percent', 80, 0, 100)], [
                        ['label' => 'Customer satisfaction', 'value' => 98],
                        ['label' => 'Time saved', 'value' => 85],
                        ['label' => 'Faster launch', 'value' => 70],
                    ]),
                ],
            ]),
            new ConfigBlock('team', 'Team', 'users', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Meet the team'),
                    Field::text('sub', 'Subheading', ''),
                    Field::repeater('items', 'People', [Field::text('name', 'Name', ''), Field::text('role', 'Role', ''), Field::image('photo', 'Photo URL', '')], [
                        ['name' => 'Alex Rivera', 'role' => 'Founder & CEO'],
                        ['name' => 'Sam Chen', 'role' => 'Head of Product'],
                        ['name' => 'Jordan Lee', 'role' => 'Lead Engineer'],
                    ]),
                ],
            ]),
            new ConfigBlock('story', 'Story / Mission', 'book-open', BlockCategory::Content, schema: [
                'content' => [
                    Field::text('eyebrow', 'Eyebrow', 'Our story'),
                    Field::text('heading', 'Heading', 'Built by people who needed it too'),
                    Field::textarea('body', 'Body', "We started this because we were frustrated with the alternatives.\n\nToday, thousands of people rely on it every single day."),
                    Field::image('image', 'Image URL', ''),
                    Field::select('imageSide', 'Image side', ['left' => 'Left', 'right' => 'Right'], 'left'),
                    Field::text('signature', 'Signature', ''),
                ],
            ]),

            // ─── Media ─────────────────────────────────────────────────────────
            new ConfigBlock('image', 'Image', 'photo', BlockCategory::Media, schema: [
                'content' => [
                    Field::image('src', 'Image URL', ''),
                    Field::text('alt', 'Alt text', ''),
                    Field::select('width', 'Width', ['full' => 'Full', 'wide' => 'Wide', 'medium' => 'Medium'], 'wide'),
                ],
            ]),
            new ConfigBlock('video', 'Video', 'play-circle', BlockCategory::Media, schema: [
                'content' => [
                    Field::text('title', 'Title', 'Watch the demo'),
                    Field::text('subtitle', 'Subtitle', 'See exactly what you get before you buy.'),
                    Field::link('url', 'Embed URL', ''),
                ],
            ]),
            new ConfigBlock('logos', 'Logo cloud', 'building-office-2', BlockCategory::Media, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Trusted by teams at'),
                    Field::repeater('items', 'Logos', [Field::text('name', 'Name', '')], [
                        ['name' => 'Acme Inc'], ['name' => 'Globex'], ['name' => 'Umbrella'], ['name' => 'Initech'],
                    ]),
                ],
            ]),

            new ConfigBlock('gallery', 'Gallery', 'rectangle-stack', BlockCategory::Media, schema: [
                'content' => [
                    Field::text('heading', 'Heading', ''),
                    Field::select('layout', 'Layout', ['grid' => 'Grid', 'masonry' => 'Masonry', 'carousel' => 'Carousel'], 'grid'),
                    Field::number('cols', 'Columns', 3, 2, 5),
                    Field::toggle('lightbox', 'Click to zoom (lightbox)', true),
                    Field::toggle('captions', 'Show captions', false),
                    Field::toggle('filter', 'Category filter tabs', false),
                    Field::number('perLoad', 'Show at first (0 = all)', 0, 0, 60),
                    Field::repeater('items', 'Images', [
                        Field::image('src', 'Image URL', ''),
                        Field::text('alt', 'Alt text', ''),
                        Field::text('caption', 'Caption', ''),
                        Field::text('category', 'Category', ''),
                    ], [
                        ['src' => ''], ['src' => ''], ['src' => ''],
                    ]),
                ],
            ]),
            new ConfigBlock('slider', 'Carousel', 'film', BlockCategory::Media, schema: [
                'content' => [
                    Field::text('heading', 'Heading', ''),
                    Field::repeater('items', 'Slides', [Field::image('src', 'Image URL', ''), Field::text('caption', 'Caption', '')], [
                        ['src' => '', 'caption' => 'Slide one'], ['src' => '', 'caption' => 'Slide two'],
                    ]),
                ],
            ]),

            // ─── Social proof ──────────────────────────────────────────────────
            new ConfigBlock('stats', 'Stats', 'chart-bar', BlockCategory::SocialProof, schema: [
                'content' => [
                    Field::repeater('items', 'Stats', [Field::text('value', 'Value', ''), Field::text('label', 'Label', '')], [
                        ['value' => '12,400+', 'label' => 'Happy customers'],
                        ['value' => '4.9/5', 'label' => 'Average rating'],
                        ['value' => '60+', 'label' => 'Countries'],
                        ['value' => '24/7', 'label' => 'Support'],
                    ]),
                ],
            ]),
            new ConfigBlock('testimonials', 'Testimonials', 'chat-bubble-left-ellipsis', BlockCategory::SocialProof, schema: [
                'content' => [
                    Field::variant([
                        'cards' => 'Cards', 'carousel' => 'Carousel', 'minimal' => 'Minimal', 'single' => 'Single quote',
                    ], 'cards'),
                    Field::text('eyebrow', 'Eyebrow', 'Reviews'),
                    Field::text('heading', 'Heading', 'Loved by buyers'),
                    Field::number('cols', 'Columns', 2, 1, 3),
                    Field::repeater('items', 'Testimonials', [
                        Field::text('name', 'Name', ''), Field::text('role', 'Role', ''),
                        Field::textarea('quote', 'Quote', ''), Field::image('photo', 'Photo', ''),
                    ], [
                        ['name' => 'Aisha K.', 'role' => 'Indie founder', 'quote' => 'I launched my MVP in 4 days — paid for itself instantly.'],
                        ['name' => 'Tanvir H.', 'role' => 'Agency lead', 'quote' => 'Huge time saver on every client project.'],
                    ]),
                    Field::toggle('dark', 'Dark background', false),
                ],
            ]),
            new ConfigBlock('guarantee', 'Guarantee', 'shield-check', BlockCategory::SocialProof, schema: [
                'content' => [Field::textarea('text', 'Text', '14-day money-back guarantee — buy with complete confidence.')],
            ]),
            new ConfigBlock('trust-badges', 'Trust badges', 'lock-closed', BlockCategory::SocialProof, schema: [
                'content' => [
                    Field::toggle('secure', 'Secure checkout', true),
                    Field::toggle('refund', '14-day refund', true),
                    Field::toggle('instant', 'Instant access', true),
                ],
            ]),

            new ConfigBlock('quote', 'Quote spotlight', 'chat-bubble-bottom-center-text', BlockCategory::SocialProof, schema: [
                'content' => [
                    Field::textarea('quote', 'Quote', 'This is hands-down the best purchase I’ve made this year.'),
                    Field::text('name', 'Name', 'Morgan T.'),
                    Field::text('role', 'Role', 'Small business owner'),
                    Field::image('photo', 'Photo URL', ''),
                ],
            ]),
            new ConfigBlock('video-testimonials', 'Video testimonials', 'video-camera', BlockCategory::SocialProof, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Hear it from customers'),
                    Field::repeater('items', 'Videos', [Field::link('url', 'Embed URL', ''), Field::text('name', 'Name', ''), Field::text('role', 'Role', '')], [
                        ['url' => '', 'name' => 'Jamie', 'role' => 'Verified buyer'],
                        ['url' => '', 'name' => 'Priya', 'role' => 'Verified buyer'],
                    ]),
                ],
            ]),
            new ConfigBlock('case-studies', 'Case studies', 'trophy', BlockCategory::SocialProof, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Real results'),
                    Field::repeater('items', 'Studies', [Field::text('metric', 'Metric', ''), Field::text('title', 'Title', ''), Field::textarea('body', 'Body', ''), Field::text('name', 'Attribution', '')], [
                        ['metric' => '+312%', 'title' => 'Revenue in 90 days', 'body' => 'How one seller tripled sales after switching.', 'name' => 'Acme Store'],
                        ['metric' => '18 hrs', 'title' => 'Saved every week', 'body' => 'Automation replaced hours of manual work.', 'name' => 'Globex'],
                    ]),
                ],
            ]),

            // ─── Urgency ───────────────────────────────────────────────────────
            new ConfigBlock('countdown', 'Countdown', 'clock', BlockCategory::Urgency, schema: [
                'content' => [
                    Field::text('label', 'Label', 'Offer ends in'),
                    Field::number('hours', 'Hours from now', 24, 1, 720),
                ],
            ]),
            new ConfigBlock('announcement-bar', 'Announcement bar', 'megaphone', BlockCategory::Urgency, schema: [
                'content' => [
                    Field::text('text', 'Text', '🎉 Launch week — save 30% with code LAUNCH'),
                    Field::text('cta', 'Link label', 'Shop now'),
                    Field::link('href', 'Link URL', '#'),
                ],
            ]),

            // ─── Commerce (dynamic — read live product/offer data) ──────────────
            new ConfigBlock('hero', 'Hero', 'sparkles', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::variant([
                        'centered' => 'Centered', 'split' => 'Split', 'minimal' => 'Minimal',
                        'gradient' => 'Gradient', 'showcase' => 'Showcase',
                    ], 'centered'),
                    Field::text('eyebrow', 'Eyebrow', ''),
                    Field::text('headline', 'Headline', 'A headline that sells the outcome'),
                    Field::text('tagline', 'Tagline', 'A short, punchy value line.'),
                    Field::textarea('desc', 'Description', 'Describe what the buyer gets and why it’s worth it.'),
                    Field::text('btn', 'Button label', 'Buy now'),
                    Field::image('image', 'Hero image', ''), // used by Split / Showcase
                    Field::toggle('showRating', 'Show rating badge', true),
                    Field::toggle('showTrust', 'Show trust row', true),
                    Field::toggle('dark', 'Dark background', false),
                ],
            ]),
            new ConfigBlock('product', 'Single product', 'cube', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::text('name', 'Name override', ''),
                    Field::textarea('summary', 'Summary override', ''),
                    Field::text('btn', 'Button label', 'Buy now'),
                    Field::text('note', 'Note', 'Instant, secure checkout.'),
                ],
            ]),
            new ConfigBlock('buy-button', 'Buy button', 'shopping-cart', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::text('label', 'Label', 'Buy now'),
                    Field::toggle('showPrice', 'Show price', true),
                    Field::select('align', 'Align', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'center'),
                ],
            ]),
            new ConfigBlock('order-bump', 'Order bump', 'plus-circle', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::text('note', 'Note', 'Recommended add-on'),
                ],
            ]),
            new ConfigBlock('cta', 'Final CTA', 'megaphone', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Get it today'),
                    Field::text('btn', 'Button label', ''),
                ],
            ]),
            new ConfigBlock('pricing', 'Pricing', 'currency-dollar', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::variant(['cards' => 'Cards', 'minimal' => 'Minimal', 'compact' => 'Compact'], 'cards'),
                    Field::text('heading', 'Heading', 'Simple, honest pricing'),
                    Field::text('sub', 'Subheading', 'Pick the plan that fits. Upgrade anytime.'),
                    Field::number('cols', 'Columns', 3, 2, 4),
                    Field::toggle('billingToggle', 'Monthly / Yearly toggle', false),
                    Field::text('yearlyNote', 'Yearly note', 'Save 20% billed yearly'),
                    Field::repeater('items', 'Plans', [
                        Field::text('name', 'Name', ''),
                        Field::text('price', 'Monthly price', ''),
                        Field::text('priceYearly', 'Yearly price', ''),
                        Field::text('period', 'Period', ''),
                        Field::text('desc', 'Description', ''),
                        Field::textarea('features', 'Features (one per line)', ''),
                        Field::text('cta', 'Button label', 'Choose plan'),
                        Field::text('badge', 'Badge', ''),
                        Field::toggle('featured', 'Highlight this plan', false),
                    ], [
                        ['name' => 'Starter', 'price' => '$19', 'priceYearly' => '$15', 'period' => '/mo', 'desc' => 'For getting started', 'features' => "1 project\nEmail support\nBasic analytics", 'cta' => 'Start now'],
                        ['name' => 'Pro', 'price' => '$49', 'priceYearly' => '$39', 'period' => '/mo', 'desc' => 'For growing teams', 'features' => "Unlimited projects\nPriority support\nAdvanced analytics\nCustom domain", 'cta' => 'Go Pro', 'badge' => 'Most popular', 'featured' => true],
                        ['name' => 'Scale', 'price' => '$99', 'priceYearly' => '$79', 'period' => '/mo', 'desc' => 'For high volume', 'features' => "Everything in Pro\nDedicated manager\nSLA & onboarding", 'cta' => 'Contact us'],
                    ]),
                    Field::toggle('dark', 'Dark background', false),
                ],
            ]),
            new ConfigBlock('product-grid', 'Products (grid)', 'squares-plus', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Shop the collection'),
                    Field::text('sub', 'Subheading', ''),
                    Field::number('cols', 'Columns', 3, 2, 4),
                    Field::number('limit', 'Max products', 6, 1, 24),
                    Field::toggle('showPrice', 'Show price', true),
                    Field::toggle('showSummary', 'Show summary', true),
                    Field::text('cta', 'Card button', 'View'),
                ],
            ]),
            new ConfigBlock('offer-banner', 'Offer banner', 'tag', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::text('eyebrow', 'Eyebrow', 'Today only'),
                    Field::text('heading', 'Heading', 'Save 30% for a limited time'),
                    Field::text('sub', 'Subheading', 'This deal disappears at midnight — grab it now.'),
                    Field::text('btn', 'Button label', 'Claim offer'),
                ],
            ]),
            new ConfigBlock('cta-banner', 'CTA banner', 'rocket-launch', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::variant([
                        'gradient' => 'Gradient', 'simple' => 'Simple', 'dark' => 'Dark',
                        'card' => 'Floating card', 'split' => 'Split',
                    ], 'gradient'),
                    Field::text('eyebrow', 'Eyebrow', ''),
                    Field::text('heading', 'Heading', 'Ready to get started?'),
                    Field::text('sub', 'Subheading', 'Join thousands already using it today.'),
                    Field::text('btn', 'Button label', 'Get started'),
                    Field::text('note', 'Small note', ''),
                ],
            ]),
            new ConfigBlock('sticky-cta', 'Sticky CTA bar', 'bolt', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::text('text', 'Text', ''),
                    Field::text('btn', 'Button label', 'Buy now'),
                ],
            ]),

            // ─── Forms ─────────────────────────────────────────────────────────
            new ConfigBlock('lead-capture', 'Lead capture', 'envelope', BlockCategory::Form, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Get the free guide'),
                    Field::text('sub', 'Subheading', 'Drop your email and we’ll send it over.'),
                    Field::text('placeholder', 'Placeholder', 'you@email.com'),
                    Field::text('btn', 'Button label', 'Send it to me'),
                    Field::link('action', 'Form action URL', ''),
                    Field::text('note', 'Small print', 'No spam. Unsubscribe anytime.'),
                ],
            ]),
            new ConfigBlock('contact', 'Contact form', 'inbox-arrow-down', BlockCategory::Form, schema: [
                'content' => [
                    Field::text('heading', 'Heading', 'Get in touch'),
                    Field::text('sub', 'Subheading', 'We usually reply within a few hours.'),
                    Field::text('btn', 'Button label', 'Send message'),
                    Field::link('action', 'Form action URL', ''),
                ],
            ]),
        ];
    }

    /**
     * A curated icon picker for repeater rows (features, benefits, …). Values map
     * to Heroicons via the `<x-builder.icon>` component (which is fatal-safe).
     *
     * @return array<string, mixed>
     */
    private static function iconField(string $key = 'icon', string $default = 'sparkles'): array
    {
        return Field::select($key, 'Icon', [
            'sparkles' => 'Sparkles', 'bolt' => 'Bolt', 'check-circle' => 'Check', 'shield-check' => 'Shield',
            'rocket-launch' => 'Rocket', 'star' => 'Star', 'heart' => 'Heart', 'gift' => 'Gift',
            'clock' => 'Clock', 'chart-bar' => 'Chart', 'globe-alt' => 'Globe', 'credit-card' => 'Card',
            'truck' => 'Shipping', 'user-group' => 'Team', 'chat-bubble-left-right' => 'Chat',
            'arrow-path' => 'Sync / Updates', 'light-bulb' => 'Idea', 'lock-closed' => 'Lock',
            'trophy' => 'Trophy', 'academic-cap' => 'Learn', 'banknotes' => 'Money', 'cog-6-tooth' => 'Settings',
        ], $default);
    }

    /**
     * Platform picker for a social-links repeater row — the value maps to a brand
     * glyph in the `<x-builder.social-icon>` component (header + footer).
     *
     * @return array<string, mixed>
     */
    private static function socialField(): array
    {
        return Field::select('platform', 'Platform', [
            'website' => 'Website',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'x' => 'X (Twitter)',
            'youtube' => 'YouTube',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'pinterest' => 'Pinterest',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'github' => 'GitHub',
        ], 'website');
    }
}
