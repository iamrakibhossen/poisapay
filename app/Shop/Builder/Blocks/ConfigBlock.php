<?php

declare(strict_types=1);

namespace App\Shop\Builder\Blocks;

use App\Shop\Builder\BlockCategory;
use App\Shop\Builder\BlockLibrary;

/**
 * A block defined entirely by data — type, label, icon, category, schema, and
 * whether it's a container. Because every block renders from a Blade partial named
 * after its type, the vast majority of blocks need no bespoke PHP: they are just a
 * config entry (see {@see BlockLibrary}) + a Blade partial.
 *
 * A block that genuinely needs PHP logic can still extend {@see AbstractBlock}
 * directly and override render().
 */
final class ConfigBlock extends AbstractBlock
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $schema
     * @param  list<string>  $allowedChildren
     */
    public function __construct(
        private readonly string $type,
        private readonly string $label,
        private readonly string $icon,
        private readonly BlockCategory $category,
        private readonly array $schema = [],
        private readonly bool $container = false,
        private readonly array $allowedChildren = [],
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function category(): BlockCategory
    {
        return $this->category;
    }

    public function isContainer(): bool
    {
        return $this->container;
    }

    public function allowedChildren(): array
    {
        return $this->allowedChildren;
    }

    public function schema(): array
    {
        return $this->schema;
    }
}
