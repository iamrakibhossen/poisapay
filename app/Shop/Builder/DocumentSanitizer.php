<?php

declare(strict_types=1);

namespace App\Shop\Builder;

/**
 * Hardens an untrusted document coming from the editor before it is persisted or
 * rendered: it re-shapes every node to the known schema, caps total node count and
 * nesting depth (a cheap DoS guard), and drops anything unexpected. Unknown block
 * *types* are kept (the renderer skips them) so a newer client stays
 * forward-compatible, but the structure is always well-formed.
 */
final class DocumentSanitizer
{
    private const MAX_NODES = 800;

    private const MAX_DEPTH = 8;

    private const STYLE_BREAKPOINTS = ['base', 'tablet', 'mobile'];

    private int $count = 0;

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed> a clean v2 document array
     */
    public function clean(array $raw): array
    {
        $this->count = 0;

        $globals = array_replace(
            BuilderDocument::defaultGlobals(),
            $this->arr($raw['globals'] ?? []),
        );

        $rootRaw = $this->arr($raw['root'] ?? []);
        $children = [];
        foreach ($this->arr($rootRaw['children'] ?? []) as $child) {
            if (is_array($child)) {
                $children[] = $this->node($child, 1);
            }
        }

        return [
            'schema' => BuilderDocument::SCHEMA,
            'root' => ['id' => 'root', 'type' => 'page', 'children' => array_filter($children)],
            'globals' => $globals,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function node(array $raw, int $depth): ?array
    {
        if ($depth > self::MAX_DEPTH || ++$this->count > self::MAX_NODES) {
            return null;
        }

        $type = is_string($raw['type'] ?? null) ? substr($raw['type'], 0, 40) : 'section';
        $id = is_string($raw['id'] ?? null) && preg_match('/^[a-z0-9_]{1,40}$/', $raw['id'])
            ? $raw['id']
            : BuilderNode::newId();

        $children = [];
        foreach ($this->arr($raw['children'] ?? []) as $child) {
            if (is_array($child)) {
                $node = $this->node($child, $depth + 1);
                if ($node !== null) {
                    $children[] = $node;
                }
            }
        }

        return [
            'id' => $id,
            'type' => $type,
            'props' => $this->arr($raw['props'] ?? []),
            'style' => $this->style($this->arr($raw['style'] ?? [])),
            'visibility' => $this->visibility($this->arr($raw['visibility'] ?? [])),
            'children' => $children,
            'meta' => $this->arr($raw['meta'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, array<string, mixed>>
     */
    private function style(array $style): array
    {
        $out = [];
        foreach (self::STYLE_BREAKPOINTS as $bp) {
            $out[$bp] = $this->arr($style[$bp] ?? []);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $vis
     * @return array<string, bool>
     */
    private function visibility(array $vis): array
    {
        return [
            'desktop' => (bool) ($vis['desktop'] ?? true),
            'tablet' => (bool) ($vis['tablet'] ?? true),
            'mobile' => (bool) ($vis['mobile'] ?? true),
        ];
    }

    /**
     * @param  mixed  $v
     * @return array<string, mixed>
     */
    private function arr($v): array
    {
        return is_array($v) ? $v : [];
    }
}
