<?php

declare(strict_types=1);

namespace App\Shop\Builder;

/**
 * Upgrades a legacy v1 sales-page document (a flat `[{type, enabled, content}]`
 * list + a separate {accent,btn,font} theme) into the v2 block tree, in memory,
 * every time a page is read. Existing published pages therefore keep rendering
 * identically with zero data migration — legacy block *type names and content
 * shapes are preserved verbatim as node props*, so the ported Blade partials match
 * the old output pixel-for-pixel.
 */
final class SchemaMigrator
{
    /**
     * @param  mixed  $sections  the raw `sections`/`draft` jsonb (v1 list or v2 doc)
     * @param  array<string, mixed>  $theme  the legacy theme jsonb (v1 only)
     */
    public function toDocument(mixed $sections, array $theme = []): BuilderDocument
    {
        $data = is_array($sections) ? $sections : [];

        // Already v2 — a document object with a root node.
        if (isset($data['schema']) || isset($data['root'])) {
            return BuilderDocument::fromArray($data);
        }

        // Legacy v1: a flat, ordered list of enabled/disabled typed sections.
        $children = [];
        foreach (array_values($data) as $section) {
            if (! is_array($section) || empty($section['type'])) {
                continue;
            }
            $enabled = (bool) ($section['enabled'] ?? true);

            $children[] = [
                'id' => BuilderNode::newId(),
                'type' => (string) $section['type'],
                'props' => $this->normalizeLegacyProps((string) $section['type'], $section['content'] ?? []),
                'style' => ['base' => [], 'tablet' => [], 'mobile' => []],
                'visibility' => [
                    'desktop' => $enabled,
                    'tablet' => $enabled,
                    'mobile' => $enabled,
                ],
                'meta' => ['name' => ucfirst((string) $section['type'])],
                'children' => [],
            ];
        }

        return BuilderDocument::fromArray([
            'schema' => BuilderDocument::SCHEMA,
            'root' => [
                'id' => 'root',
                'type' => 'page',
                'children' => $children,
            ],
            'globals' => $this->globalsFromTheme($theme),
        ]);
    }

    /**
     * Legacy content shapes are heterogeneous — some sections stored a keyed map
     * (hero, cta…), some a bare list (stats, faq…), some a scalar (guarantee,
     * countdown). Normalise them into the keyed props the v2 blocks expect, so the
     * ported Blade partials read a single, predictable shape.
     *
     * @return array<string, mixed>
     */
    private function normalizeLegacyProps(string $type, mixed $content): array
    {
        // List-typed legacy sections → { items: [...] }.
        $listTypes = ['stats', 'logos', 'features', 'benefits', 'steps', 'bonuses', 'testimonials', 'faq'];
        if (in_array($type, $listTypes, true)) {
            return ['items' => is_array($content) ? array_values($content) : []];
        }

        // Scalar-typed legacy sections → a single text field.
        if ($type === 'guarantee') {
            return ['text' => is_array($content) ? implode(' ', $content) : (string) $content];
        }
        if ($type === 'countdown') {
            return ['label' => is_array($content) ? implode(' ', $content) : (string) $content];
        }

        // Keyed sections (hero, product, video, cta) pass through.
        return is_array($content) ? $content : [];
    }

    /**
     * Map the v1 theme onto v2 design tokens so brand colour / font / button shape
     * carry over.
     *
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    private function globalsFromTheme(array $theme): array
    {
        $globals = BuilderDocument::defaultGlobals();

        if (! empty($theme['accent'])) {
            $globals['colors']['brand'] = (string) $theme['accent'];
        }
        if (! empty($theme['font'])) {
            $globals['typography']['font'] = (string) $theme['font'];
        }
        if (! empty($theme['btn'])) {
            $globals['buttons']['radius'] = (string) $theme['btn'];
        }

        return $globals;
    }
}
