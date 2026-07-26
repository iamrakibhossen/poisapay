<?php

declare(strict_types=1);

namespace App\Shop\Builder\Templates;

use App\Shop\Builder\BuilderDocument;

/**
 * A terse node/document builder for {@see TemplateLibrary}. Templates only set the
 * props they want to override — every other prop falls back to the block's schema
 * default at render time, so a template stays short and readable.
 */
final class Tpl
{
    private static int $seq = 0;

    /**
     * A single block node. Children are other {@see self::n()} nodes.
     *
     * @param  array<string, mixed>  $props
     * @param  list<array<string, mixed>>  $children
     * @param  array<string, mixed>  $style  base-breakpoint style tokens
     * @return array<string, mixed>
     */
    public static function n(string $type, array $props = [], array $children = [], array $style = []): array
    {
        return [
            'id' => 'b_t'.str_pad((string) (++self::$seq), 8, '0', STR_PAD_LEFT),
            'type' => $type,
            'props' => $props,
            'style' => ['base' => $style, 'tablet' => [], 'mobile' => []],
            'visibility' => ['desktop' => true, 'tablet' => true, 'mobile' => true],
            'children' => $children,
            'meta' => [],
        ];
    }

    /**
     * Wrap sections into a full v2 document, optionally overriding globals (brand).
     *
     * @param  list<array<string, mixed>>  $children
     * @param  array<string, mixed>  $globals
     * @return array<string, mixed>
     */
    public static function doc(array $children, array $globals = []): array
    {
        return [
            'schema' => BuilderDocument::SCHEMA,
            'root' => ['id' => 'root', 'type' => 'page', 'children' => $children],
            'globals' => array_replace_recursive(BuilderDocument::defaultGlobals(), $globals),
        ];
    }

    /** @return array<string, mixed> brand-colour globals override */
    public static function brand(string $hex): array
    {
        return ['colors' => ['brand' => $hex]];
    }
}
