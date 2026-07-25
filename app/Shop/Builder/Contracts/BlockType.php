<?php

declare(strict_types=1);

namespace App\Shop\Builder\Contracts;

use App\Shop\Builder\BlockCategory;
use App\Shop\Builder\BuilderNode;
use App\Shop\Builder\RenderContext;

/**
 * One block type. The `schema()` is the single source of truth: it drives the
 * generated property panel, input validation, and the block's default props.
 * Adding a block to the builder = one class implementing this + one Blade partial
 * at resources/views/builder/blocks/{type}.blade.php.
 */
interface BlockType
{
    /** Stable machine name, e.g. 'hero'. Must match the Blade partial filename. */
    public function type(): string;

    /** Human label for the palette + layers tree. */
    public function label(): string;

    /** Heroicon name (outline) shown in the palette. */
    public function icon(): string;

    public function category(): BlockCategory;

    /** True for section/row/column — nodes that hold children. */
    public function isContainer(): bool;

    /**
     * Container blocks may restrict which child types they accept (machine names),
     * or return [] for "any". Ignored for leaf blocks.
     *
     * @return list<string>
     */
    public function allowedChildren(): array;

    /**
     * Field definitions grouped by panel tab, e.g.
     *   ['content' => [Field::text('headline', 'Headline'), ...], 'style' => [...]]
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function schema(): array;

    /**
     * Starting props for a freshly-inserted block. Derived from the schema
     * defaults unless overridden.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * Render this node to HTML. Container blocks receive their already-rendered
     * children as $childrenHtml and should place it where children belong.
     */
    public function render(BuilderNode $node, RenderContext $ctx, string $childrenHtml = ''): string;
}
