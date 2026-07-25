<?php

declare(strict_types=1);

namespace App\Sell\Builder;

/**
 * The whole page document: a schema version, the root node (a `page` container
 * whose children are sections), and the global design tokens. This is what lives
 * in `sell_sales_pages.draft` / `.sections` (jsonb).
 */
final class BuilderDocument
{
    public const SCHEMA = 2;

    /**
     * @param  array<string, mixed>  $globals
     */
    public function __construct(
        private readonly BuilderNode $root,
        private readonly array $globals = [],
        public readonly int $schema = self::SCHEMA,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $root = isset($data['root'])
            ? BuilderNode::fromArray((array) $data['root'])
            : new BuilderNode(id: 'root', type: 'page');

        return new self(
            root: $root,
            globals: array_replace(self::defaultGlobals(), (array) ($data['globals'] ?? [])),
            schema: (int) ($data['schema'] ?? self::SCHEMA),
        );
    }

    /** A fresh, empty page document. */
    public static function blank(): self
    {
        return new self(
            root: new BuilderNode(id: 'root', type: 'page'),
            globals: self::defaultGlobals(),
        );
    }

    public function root(): BuilderNode
    {
        return $this->root;
    }

    /** @return array<string, mixed> */
    public function globals(): array
    {
        return $this->globals;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => $this->schema,
            'root' => $this->root->toArray(),
            'globals' => $this->globals,
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultGlobals(): array
    {
        return [
            'colors' => [
                'brand' => '#2563eb',
                'ink' => '#0f172a',
                'muted' => '#64748b',
                'bg' => '#ffffff',
                'soft' => '#f8fafc',
            ],
            'typography' => ['font' => 'Inter', 'scale' => 'default'],
            'buttons' => ['radius' => 'rounded', 'style' => 'solid'],
            'radius' => ['card' => '16'],
            'spacing' => ['section' => 'comfortable'],
        ];
    }
}
