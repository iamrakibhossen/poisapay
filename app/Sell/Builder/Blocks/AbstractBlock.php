<?php

declare(strict_types=1);

namespace App\Sell\Builder\Blocks;

use App\Sell\Builder\BuilderNode;
use App\Sell\Builder\Contracts\BlockType;
use App\Sell\Builder\Field;
use App\Sell\Builder\RenderContext;
use Illuminate\Support\Facades\View;

/**
 * Base for every block. Concrete blocks usually only declare type/label/icon/
 * category/schema; rendering resolves the matching Blade partial automatically.
 */
abstract class AbstractBlock implements BlockType
{
    public function isContainer(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function allowedChildren(): array
    {
        return [];
    }

    /** Default props are derived from the schema unless a block overrides this. */
    public function defaults(): array
    {
        return Field::defaultsFrom($this->schema());
    }

    /**
     * Render the block's Blade partial (resources/views/builder/blocks/{type}.blade.php)
     * with a consistent variable surface: $node, $ctx, $children, $props.
     */
    public function render(BuilderNode $node, RenderContext $ctx, string $childrenHtml = ''): string
    {
        return View::make("builder.blocks.{$this->type()}", [
            'node' => $node,
            'ctx' => $ctx,
            'children' => $childrenHtml,
            'props' => array_merge($this->defaults(), $node->props),
        ])->render();
    }
}
