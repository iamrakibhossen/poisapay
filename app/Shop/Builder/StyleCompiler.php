<?php

declare(strict_types=1);

namespace App\Shop\Builder;

/**
 * Compiles the block tree's per-node style tokens into a single scoped stylesheet,
 * keyed by node id (`#b_xxxx { … }`) with desktop-first responsive overrides. This
 * keeps rendering fully server-side, so the Redis-cached public HTML+CSS story is
 * unchanged and the editor iframe shows the identical result.
 *
 * Two families of style keys:
 *   • simple 1:1 declarations in {@see self::$decl} (padding/margin/width/radius/…);
 *   • composites consumed by {@see composeBackground()} / {@see composeBorder()} and
 *     the per-node entrance animation + custom-CSS handlers in {@see walk()}.
 *
 * Every string value passes through {@see clean()} (or {@see url()} for images) so a
 * hostile document can never break out of its scoped rule — the one CSS-injection
 * surface the editor exposes.
 */
final class StyleCompiler
{
    /** max-width thresholds for the two override breakpoints. */
    private const TABLET = 1024;

    private const MOBILE = 640;

    public const ANIMATIONS = ['fade', 'up', 'down', 'left', 'right', 'zoom'];

    private const BORDER_STYLES = ['solid', 'dashed', 'dotted', 'double', 'none'];

    /** Keys handled by composites / node-level hooks — skipped by the flat $decl loop. */
    private const COMPOSITE_KEYS = [
        'bg', 'bgImage', 'bgSize', 'bgPosition', 'bgRepeat', 'overlay', 'gradient', 'parallax',
        'borderWidth', 'borderStyle', 'borderColor',
        'anim', 'animDur', 'animDelay', 'customCss',
    ];

    /**
     * key => fn(value): css-declaration. Spacing values are px when numeric.
     *
     * @var array<string, callable(mixed): ?string>
     */
    private array $decl;

    public function __construct()
    {
        $px = fn (mixed $v): string => is_numeric($v) ? "{$v}px" : $this->clean((string) $v);
        $len = fn (mixed $v): string => $v === 'full' ? '100%' : $px($v);

        $this->decl = [
            'padY' => fn ($v) => 'padding-top:'.$px($v).';padding-bottom:'.$px($v),
            'padX' => fn ($v) => 'padding-left:'.$px($v).';padding-right:'.$px($v),
            'padTop' => fn ($v) => 'padding-top:'.$px($v),
            'padBottom' => fn ($v) => 'padding-bottom:'.$px($v),
            'padLeft' => fn ($v) => 'padding-left:'.$px($v),
            'padRight' => fn ($v) => 'padding-right:'.$px($v),
            'marginY' => fn ($v) => 'margin-top:'.$px($v).';margin-bottom:'.$px($v),
            'marginX' => fn ($v) => 'margin-left:'.$px($v).';margin-right:'.$px($v),
            'marginTop' => fn ($v) => 'margin-top:'.$px($v),
            'marginBottom' => fn ($v) => 'margin-bottom:'.$px($v),
            'marginLeft' => fn ($v) => 'margin-left:'.$px($v),
            'marginRight' => fn ($v) => 'margin-right:'.$px($v),
            'gap' => fn ($v) => 'gap:'.$px($v),
            'width' => fn ($v) => 'width:'.$len($v),
            'maxWidth' => fn ($v) => 'max-width:'.$len($v),
            'minHeight' => fn ($v) => 'min-height:'.$px($v),
            'radius' => fn ($v) => 'border-radius:'.$px($v),
            'align' => fn ($v) => in_array($v, ['left', 'center', 'right'], true) ? 'text-align:'.$v : null,
            'justify' => fn ($v) => 'justify-content:'.$this->flexAlign($v),
            'items' => fn ($v) => 'align-items:'.$this->flexAlign($v),
            'color' => fn ($v) => 'color:'.$this->color($v),
            'shadow' => fn ($v) => ($s = $this->shadow($v)) ? 'box-shadow:'.$s : null,
            'opacity' => fn ($v) => is_numeric($v) ? 'opacity:'.$this->opacity($v) : null,
            'zIndex' => fn ($v) => is_numeric($v) ? 'position:relative;z-index:'.(int) $v : null,
        ];
    }

    /** The full stylesheet for a document root (no <style> tag). */
    public function compile(BuilderNode $root): string
    {
        $base = [];
        $tablet = [];
        $mobile = [];

        $this->walk($root, $base, $tablet, $mobile);

        $css = implode('', $base);
        if ($tablet !== []) {
            $css .= '@media(max-width:'.self::TABLET.'px){'.implode('', $tablet).'}';
        }
        if ($mobile !== []) {
            $css .= '@media(max-width:'.self::MOBILE.'px){'.implode('', $mobile).'}';
        }

        return $css;
    }

    /**
     * :root design-token variables from the document globals, so token references
     * (`var(--pp-brand)`) resolve and a global change reflows the whole page.
     *
     * @param  array<string, mixed>  $globals
     */
    public function rootVariables(array $globals): string
    {
        $vars = [];
        foreach ((array) ($globals['colors'] ?? []) as $name => $hex) {
            if (is_string($hex) && $hex !== '') {
                $vars[] = '--pp-'.$this->slug($name).':'.$this->clean($hex);
            }
        }
        $font = $globals['typography']['font'] ?? null;
        if (is_string($font) && $font !== '') {
            $vars[] = "--pp-font:'".addslashes($this->clean($font))."', ui-sans-serif, system-ui, sans-serif";
        }
        $radius = $globals['radius']['card'] ?? null;
        if ($radius !== null) {
            $vars[] = '--pp-radius:'.(is_numeric($radius) ? "{$radius}px" : $this->clean((string) $radius));
        }

        // Button corner radius derived from the chosen button shape.
        $vars[] = '--pp-btn-radius:'.match ($globals['buttons']['radius'] ?? 'rounded') {
            'pill' => '9999px',
            'square' => '2px',
            default => '12px',
        };

        // Convenience alias so ported legacy markup can keep using --pp-accent.
        if (isset($globals['colors']['brand'])) {
            $vars[] = '--pp-accent:'.$this->clean((string) $globals['colors']['brand']);
        }

        // $vars always carries at least --pp-btn-radius, so this is never empty.
        return ':root{'.implode(';', $vars).'}';
    }

    /**
     * @param  list<string>  $base
     * @param  list<string>  $tablet
     * @param  list<string>  $mobile
     */
    private function walk(BuilderNode $node, array &$base, array &$tablet, array &$mobile): void
    {
        $sel = '#'.$node->id;
        $baseStyle = $node->style['base'] ?? [];

        if (($b = $this->rules($baseStyle)) !== '') {
            $base[] = "{$sel}{{$b}}";
        }
        if (($t = $this->rules($node->style['tablet'] ?? [])) !== '') {
            $tablet[] = "{$sel}{{$t}}";
        }
        if (($m = $this->rules($node->style['mobile'] ?? [])) !== '') {
            $mobile[] = "{$sel}{{$m}}";
        }

        // Per-node custom CSS (declarations only, base bucket).
        if (($custom = $this->customCss($baseStyle['customCss'] ?? null)) !== '') {
            $base[] = "{$sel}{{$custom}}";
        }

        // Entrance animation — a scroll-triggered reveal. Hidden state applies only
        // once JS flags support (`html.pp-anim`), so with JS off the content is fully
        // visible; the observer adds `.pp-in` when the node scrolls into view; and
        // reduced-motion users always see the final state. See {@see Renderer} for the
        // `data-anim` marker and frontend.js for the IntersectionObserver.
        $anim = $baseStyle['anim'] ?? null;
        if (is_string($anim) && in_array($anim, self::ANIMATIONS, true)) {
            $dur = $this->clampInt($baseStyle['animDur'] ?? 600, 100, 4000);
            $delay = $this->clampInt($baseStyle['animDelay'] ?? 0, 0, 5000);
            $from = $this->animFrom($anim);
            $tf = $from !== '' ? ';transform:'.$from : '';
            $trans = 'transition:opacity '.$dur.'ms ease'.($from !== '' ? ',transform '.$dur.'ms ease' : '').';transition-delay:'.$delay.'ms';
            // Reveal selector is intentionally more specific than the hidden one
            // (`html.pp-anim #id.pp-in` beats `html.pp-anim #id`) so adding `.pp-in`
            // actually wins — otherwise the element stays stuck at opacity:0.
            $base[] = 'html.pp-anim '.$sel.'{opacity:0'.$tf.';'.$trans.'}';
            $base[] = 'html.pp-anim '.$sel.'.pp-in{opacity:1'.($from !== '' ? ';transform:none' : '').'}';
            $base[] = '@media(prefers-reduced-motion:reduce){html.pp-anim '.$sel.'{opacity:1;transform:none;transition:none}}';
        }

        // Per-device visibility → display:none in the matching query.
        $vis = $node->visibility;
        if (($vis['tablet'] ?? true) === false) {
            $tablet[] = "{$sel}{display:none!important}";
        }
        if (($vis['mobile'] ?? true) === false) {
            $mobile[] = "{$sel}{display:none!important}";
        }
        if (($vis['desktop'] ?? true) === false) {
            $base[] = '@media(min-width:'.(self::TABLET + 1).'px){'."{$sel}{display:none!important}".'}';
        }

        foreach ($node->children as $child) {
            $this->walk($child, $base, $tablet, $mobile);
        }
    }

    /**
     * @param  array<string, mixed>  $style
     */
    private function rules(array $style): string
    {
        $out = $this->composeBackground($style);
        foreach ($this->composeBorder($style) as $decl) {
            $out[] = $decl;
        }

        foreach ($style as $key => $value) {
            if (in_array($key, self::COMPOSITE_KEYS, true)) {
                continue;
            }
            if ($value === null || $value === '' || ! isset($this->decl[$key])) {
                continue;
            }
            $decl = ($this->decl[$key])($value);
            if ($decl !== null && $decl !== '') {
                $out[] = $decl;
            }
        }

        return implode(';', $out);
    }

    /**
     * Fold the background family (solid colour + image + overlay + gradient +
     * parallax) into a single layered `background-image` + longhands. Falls back to
     * the legacy `background:` shorthand when only a solid colour is set, so existing
     * documents compile byte-for-byte the same.
     *
     * @param  array<string, mixed>  $s
     * @return list<string>
     */
    private function composeBackground(array $s): array
    {
        $layers = [];
        if (isset($s['gradient']) && is_string($s['gradient']) && $s['gradient'] !== '') {
            $layers[] = $this->clean($s['gradient']);
        }
        if (isset($s['overlay']) && $s['overlay'] !== '') {
            $ov = $this->color($s['overlay']);
            $layers[] = "linear-gradient({$ov},{$ov})";
        }
        if (($img = $this->url($s['bgImage'] ?? null)) !== '') {
            $layers[] = "url(\"{$img}\")";
        }

        $hasSolid = isset($s['bg']) && $s['bg'] !== '';

        // A user-set background must win over a block partial's built-in inline
        // background (e.g. cta-banner's gradient) — inline styles outrank an id
        // selector, so we mark the explicit background !important.
        if ($layers === []) {
            return $hasSolid ? ['background:'.$this->color($s['bg']).'!important'] : [];
        }

        $out = ['background-image:'.implode(',', $layers).'!important'];
        if ($hasSolid) {
            $out[] = 'background-color:'.$this->color($s['bg']).'!important';
        }
        $out[] = 'background-size:'.$this->clean((string) ($s['bgSize'] ?? 'cover'));
        $out[] = 'background-position:'.$this->clean((string) ($s['bgPosition'] ?? 'center'));
        $out[] = 'background-repeat:'.$this->clean((string) ($s['bgRepeat'] ?? 'no-repeat'));
        if (($s['parallax'] ?? false)) {
            $out[] = 'background-attachment:fixed';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $s
     * @return list<string>
     */
    private function composeBorder(array $s): array
    {
        $w = $s['borderWidth'] ?? null;
        $style = $s['borderStyle'] ?? null;
        $color = $s['borderColor'] ?? null;

        $hasW = $w !== null && $w !== '';
        $hasStyle = $style !== null && $style !== '';
        $hasColor = $color !== null && $color !== '';
        if (! $hasW && ! $hasStyle && ! $hasColor) {
            return [];
        }

        $width = is_numeric($w) ? ((int) $w).'px' : ($hasW ? $this->clean((string) $w) : '1px');
        $styleName = in_array($style, self::BORDER_STYLES, true) ? $style : 'solid';
        $col = $hasColor ? $this->color($color) : 'currentColor';

        return ['border:'.$width.' '.$styleName.' '.$col];
    }

    /** Resolve a colour value: {token:'brand'} → var(--pp-brand); else scrubbed raw. */
    private function color(mixed $value): string
    {
        if (is_array($value) && isset($value['token'])) {
            return 'var(--pp-'.$this->slug((string) $value['token']).')';
        }

        return is_string($value) ? $this->clean($value) : 'transparent';
    }

    /** Sanitise a background-image URL — only http(s), root-relative or data:image. */
    private function url(mixed $v): string
    {
        if (! is_string($v) || trim($v) === '') {
            return '';
        }
        $v = trim($v);
        if (! preg_match('#^(https?:)?//#', $v) && ! str_starts_with($v, '/') && ! str_starts_with($v, 'data:image/')) {
            return '';
        }

        return str_replace(['"', "'", '\\', '<', '>', '(', ')', ' '], ['', '', '', '', '', '', '', '%20'], $v);
    }

    /** The from-state transform for an entrance animation ('' = opacity-only fade). */
    private function animFrom(string $type): string
    {
        return match ($type) {
            'up' => 'translateY(24px)',
            'down' => 'translateY(-24px)',
            'left' => 'translateX(24px)',
            'right' => 'translateX(-24px)',
            'zoom' => 'scale(.94)',
            default => '',
        };
    }

    private function flexAlign(mixed $v): string
    {
        return match ($v) {
            'left', 'top', 'start' => 'flex-start',
            'right', 'bottom', 'end' => 'flex-end',
            'between' => 'space-between',
            default => 'center',
        };
    }

    private function shadow(mixed $v): ?string
    {
        return match ($v) {
            'sm' => '0 1px 2px rgba(15,23,42,.06)',
            'md' => '0 8px 24px rgba(15,23,42,.08)',
            'lg' => '0 20px 48px rgba(15,23,42,.12)',
            'xl' => '0 32px 64px rgba(15,23,42,.16)',
            'none', false, null => null,
            default => is_string($v) ? $this->clean($v) : null,
        };
    }

    /** Normalise 0–100 or 0–1 into a clamped 0–1 opacity string. */
    private function opacity(mixed $v): string
    {
        $n = (float) $v;
        if ($n > 1) {
            $n /= 100;
        }
        $n = max(0.0, min(1.0, $n));

        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** Author-supplied declarations, kept to `prop:value;…` (no selectors / breakout). */
    private function customCss(mixed $v): string
    {
        if (! is_string($v) || trim($v) === '') {
            return '';
        }

        return trim(str_replace(['{', '}', '<', '>'], '', substr($v, 0, 2000)));
    }

    /** Strip anything that could break out of a scoped declaration. */
    private function clean(string $v): string
    {
        return trim(str_replace(['{', '}', '<', '>', ';'], '', $v));
    }

    private function clampInt(mixed $v, int $min, int $max): int
    {
        return max($min, min($max, (int) $v));
    }

    private function slug(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? $name;
    }
}
