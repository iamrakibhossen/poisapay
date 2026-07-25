<?php

declare(strict_types=1);

namespace App\Sell\Builder;

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

        return $block->render($node, $ctx, $childrenHtml);
    }
}
