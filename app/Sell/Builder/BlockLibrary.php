<?php

declare(strict_types=1);

namespace App\Sell\Builder;

use App\Sell\Builder\Blocks\ConfigBlock;
use App\Sell\SellServiceProvider;

/**
 * The declarative catalogue of every built-in block. This is the ONE place a block
 * is introduced: add an entry here + a Blade partial at
 * resources/views/builder/blocks/{type}.blade.php — no other file changes.
 *
 * {@see SellServiceProvider} feeds these into the {@see BlockRegistry}.
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
                    Field::text('brand', 'Brand name', ''), // blank → the store name
                    Field::toggle('showLogo', 'Show logo mark', true),
                    Field::repeater('links', 'Menu links', [Field::text('label', 'Label', ''), Field::link('href', 'URL', '#')], [
                        ['label' => 'Features', 'href' => '#features'],
                        ['label' => 'Pricing', 'href' => '#pricing'],
                        ['label' => 'FAQ', 'href' => '#faq'],
                    ]),
                    Field::text('cta', 'Button label', 'Buy now'),
                    Field::toggle('sticky', 'Stick to top', true),
                    Field::number('height', 'Height (px)', 64, 44, 120),
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
                    Field::text('eyebrow', 'Eyebrow', 'Features'),
                    Field::text('heading', 'Heading', 'Everything you get'),
                    Field::repeater('items', 'Features', [Field::text('title', 'Title', '')], [
                        ['title' => 'Fast setup'], ['title' => 'Beautiful UI'], ['title' => 'Great support'], ['title' => 'Lifetime updates'],
                    ]),
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
                    Field::text('heading', 'Heading', 'Questions & answers'),
                    Field::repeater('items', 'Questions', [Field::text('q', 'Question', ''), Field::textarea('a', 'Answer', '')], [
                        ['q' => 'What do I get?', 'a' => 'Instant access to everything listed, right after payment.'],
                        ['q' => 'Refund policy?', 'a' => '14-day money-back guarantee, no questions asked.'],
                    ]),
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
                    Field::text('heading', 'Heading', 'Loved by buyers'),
                    Field::repeater('items', 'Testimonials', [
                        Field::text('name', 'Name', ''), Field::text('role', 'Role', ''), Field::textarea('quote', 'Quote', ''),
                    ], [
                        ['name' => 'Aisha K.', 'role' => 'Indie founder', 'quote' => 'I launched my MVP in 4 days — paid for itself instantly.'],
                        ['name' => 'Tanvir H.', 'role' => 'Agency lead', 'quote' => 'Huge time saver on every client project.'],
                    ]),
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

            // ─── Urgency ───────────────────────────────────────────────────────
            new ConfigBlock('countdown', 'Countdown', 'clock', BlockCategory::Urgency, schema: [
                'content' => [
                    Field::text('label', 'Label', 'Offer ends in'),
                    Field::number('hours', 'Hours from now', 24, 1, 720),
                ],
            ]),

            // ─── Commerce (dynamic — read live product/offer data) ──────────────
            new ConfigBlock('hero', 'Hero', 'sparkles', BlockCategory::Commerce, schema: [
                'content' => [
                    Field::text('headline', 'Headline', 'A headline that sells the outcome'),
                    Field::text('tagline', 'Tagline', 'A short, punchy value line.'),
                    Field::textarea('desc', 'Description', 'Describe what the buyer gets and why it’s worth it.'),
                    Field::text('btn', 'Button label', 'Buy now'),
                    Field::toggle('showRating', 'Show rating badge', true),
                ],
            ]),
            new ConfigBlock('product', 'Product card', 'cube', BlockCategory::Commerce, schema: [
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
        ];
    }
}
