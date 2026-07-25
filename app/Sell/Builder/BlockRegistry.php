<?php

declare(strict_types=1);

namespace App\Sell\Builder;

use App\Sell\Builder\Contracts\BlockType;
use InvalidArgumentException;

/**
 * The catalogue of every block the builder knows about. Single lookup point for
 * the insert palette, the generated property panel, validation, and render
 * dispatch. Bound as a singleton in {@see \App\Sell\SellServiceProvider}.
 */
final class BlockRegistry
{
    /** @var array<string, BlockType> */
    private array $blocks = [];

    /**
     * @param  iterable<BlockType>  $blocks
     */
    public function __construct(iterable $blocks = [])
    {
        foreach ($blocks as $block) {
            $this->register($block);
        }
    }

    public function register(BlockType $block): void
    {
        $this->blocks[$block->type()] = $block;
    }

    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    public function get(string $type): BlockType
    {
        return $this->blocks[$type]
            ?? throw new InvalidArgumentException("Unknown builder block type: {$type}");
    }

    /** @return array<string, BlockType> */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * Palette metadata grouped by category (ordered) for the "Add block" panel.
     * Layout containers are excluded from the leaf palette by default.
     *
     * @return list<array{category: string, label: string, blocks: list<array<string, mixed>>}>
     */
    public function palette(bool $includeContainers = false): array
    {
        $groups = [];
        foreach ($this->blocks as $block) {
            if (! $includeContainers && $block->isContainer()) {
                continue;
            }
            $cat = $block->category();
            $groups[$cat->value]['category'] = $cat->value;
            $groups[$cat->value]['label'] = $cat->label();
            $groups[$cat->value]['order'] = $cat->order();
            $groups[$cat->value]['blocks'][] = [
                'type' => $block->type(),
                'label' => $block->label(),
                'icon' => $block->icon(),
                'isContainer' => $block->isContainer(),
            ];
        }

        usort($groups, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_map(static fn ($g) => [
            'category' => $g['category'],
            'label' => $g['label'],
            'blocks' => $g['blocks'],
        ], array_values($groups));
    }

    /**
     * The full schema map (type => grouped fields) — sent to the editor so the
     * property panel and client-side defaults are generated, never hand-written.
     *
     * @return array<string, array<string, mixed>>
     */
    public function schemas(): array
    {
        $out = [];
        foreach ($this->blocks as $type => $block) {
            $out[$type] = [
                'label' => $block->label(),
                'icon' => $block->icon(),
                'isContainer' => $block->isContainer(),
                'allowedChildren' => $block->allowedChildren(),
                'fields' => $block->schema(),
                'defaults' => $block->defaults(),
            ];
        }

        return $out;
    }
}
