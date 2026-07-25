<?php

declare(strict_types=1);

namespace App\Sell\Builder;

use Illuminate\Support\Str;

/**
 * A single node in the block-tree document — a thin, typed wrapper around the raw
 * jsonb array shape so blocks/renderer read it without array-key noise.
 *
 * Shape:
 *   id, type, props{}, style{ base{}, tablet{}, mobile{} },
 *   visibility{ desktop, tablet, mobile }, children[], meta{}
 */
final class BuilderNode
{
    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, array<string, mixed>>  $style
     * @param  array<string, bool>  $visibility
     * @param  list<BuilderNode>  $children
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $props = [],
        public readonly array $style = [],
        public readonly array $visibility = [],
        public readonly array $children = [],
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? self::newId()),
            type: (string) ($data['type'] ?? 'section'),
            props: (array) ($data['props'] ?? []),
            style: (array) ($data['style'] ?? []),
            visibility: (array) ($data['visibility'] ?? []),
            children: array_map(
                static fn ($child) => self::fromArray((array) $child),
                array_values((array) ($data['children'] ?? [])),
            ),
            meta: (array) ($data['meta'] ?? []),
        );
    }

    /** A stable, collision-resistant node id (`b_` + 10 url-safe chars). */
    public static function newId(): string
    {
        return 'b_'.Str::lower(Str::random(10));
    }

    /** A single prop with a fallback, dot-notation aware. */
    public function prop(string $key, mixed $default = null): mixed
    {
        return data_get($this->props, $key, $default);
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'props' => $this->props,
            'style' => $this->style,
            'visibility' => $this->visibility,
            'children' => array_map(static fn (BuilderNode $c) => $c->toArray(), $this->children),
            'meta' => $this->meta,
        ];
    }
}
