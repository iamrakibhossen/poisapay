<?php

declare(strict_types=1);

namespace App\Shop\Builder;

use Illuminate\Support\HtmlString;

/**
 * The one and only renderer — walks a block tree depth-first and produces the page
 * HTML plus its compiled scoped CSS. Reused verbatim by the public sales page and
 * the editor's iframe preview, so "what you see" always equals "what buyers get".
 *
 * Unknown block types are skipped (never fatal) so a forward-compatible document
 * degrades gracefully instead of 500-ing a live page.
 */
final class Renderer
{
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly StyleCompiler $styles,
    ) {}

    /**
     * Render a whole document to a struct: page HTML + <head> CSS.
     *
     * @return array{html: HtmlString, css: HtmlString}
     */
    public function render(BuilderDocument $doc, RenderContext $ctx): array
    {
        $root = $doc->root();

        $html = '';
        foreach ($root->children as $section) {
            $html .= $this->node($section, $ctx);
        }

        $css = $this->styles->rootVariables($doc->globals()).$this->styles->compile($root);

        return [
            'html' => new HtmlString($html),
            'css' => new HtmlString($css),
        ];
    }

    /** Render a single node (and its subtree). Public so the preview endpoint can reuse it. */
    public function node(BuilderNode $node, RenderContext $ctx): string
    {
        if (! $this->registry->has($node->type)) {
            return $ctx->editing
                ? '<!-- unknown block: '.e($node->type).' -->'
                : '';
        }

        $block = $this->registry->get($node->type);

        $childrenHtml = '';
        if ($block->isContainer()) {
            foreach ($node->children as $child) {
                $childrenHtml .= $this->node($child, $ctx);
            }
        }

        return $this->chrome($block->render($node, $ctx, $childrenHtml), $node);
    }

    /**
     * Inject the node's custom class name + anchor onto its own root element (the tag
     * bearing `id="{$node->id}"`). Centralised here so every block gains the two
     * settings without touching 28 partials; a no-op unless one is set.
     */
    private function chrome(string $html, BuilderNode $node): string
    {
        $class = $this->safeClass($node->meta['className'] ?? null);
        $anchor = $this->safeAnchor($node->meta['anchor'] ?? null);
        $anim = $node->style['base']['anim'] ?? null;
        $hasAnim = is_string($anim) && in_array($anim, StyleCompiler::ANIMATIONS, true);
        if ($class === '' && $anchor === '' && ! $hasAnim) {
            return $html;
        }

        $pattern = '/<[a-zA-Z][a-zA-Z0-9]*\b[^>]*\bid="'.preg_quote($node->id, '/').'"[^>]*>/';

        return preg_replace_callback($pattern, function (array $m) use ($class, $anchor, $hasAnim): string {
            $tag = $m[0];
            if ($class !== '') {
                $tag = preg_match('/\sclass="[^"]*"/', $tag)
                    ? preg_replace('/(\sclass=")([^"]*)(")/', '$1$2 '.$class.'$3', $tag, 1)
                    : preg_replace('/^(<[a-zA-Z0-9]+)/', '$1 class="'.$class.'"', $tag, 1);
            }
            if ($anchor !== '') {
                $tag = preg_replace('/^(<[a-zA-Z0-9]+)/', '$1 data-anchor="'.$anchor.'"', $tag, 1);
            }
            if ($hasAnim) {
                $tag = preg_replace('/^(<[a-zA-Z0-9]+)/', '$1 data-anim=""', $tag, 1);
            }

            return $tag;
        }, $html, 1) ?? $html;
    }

    private function safeClass(mixed $v): string
    {
        return is_string($v) ? trim(preg_replace('/[^a-zA-Z0-9 _-]/', '', $v) ?? '') : '';
    }

    private function safeAnchor(mixed $v): string
    {
        return is_string($v) ? trim(preg_replace('/[^a-z0-9-]/', '', strtolower($v)) ?? '', '-') : '';
    }
}
