<?php

declare(strict_types=1);

namespace App\Sell\Builder;

/**
 * Compiles the block tree's per-node style tokens into a single scoped stylesheet,
 * keyed by node id (`#b_xxxx { … }`) with desktop-first responsive overrides. This
 * keeps rendering fully server-side, so the Redis-cached public HTML+CSS story is
 * unchanged and the editor iframe shows the identical result.
 *
 * Supported style keys live in {@see self::DECL}; unknown keys are ignored so a
 * forward-compatible document never emits garbage CSS.
 */
final class StyleCompiler
{
    /** max-width thresholds for the two override breakpoints. */
    private const TABLET = 1024;

    private const MOBILE = 640;

    /**
     * key => fn(value): css-declaration. Spacing values are px when numeric.
     *
     * @var array<string, callable(mixed): ?string>
     */
    private array $decl;

    public function __construct()
    {
        $px = static fn (mixed $v): string => is_numeric($v) ? "{$v}px" : (string) $v;

        $this->decl = [
            'padY' => fn ($v) => 'padding-top:'.$px($v).';padding-bottom:'.$px($v),
            'padX' => fn ($v) => 'padding-left:'.$px($v).';padding-right:'.$px($v),
            'padTop' => fn ($v) => 'padding-top:'.$px($v),
            'padBottom' => fn ($v) => 'padding-bottom:'.$px($v),
            'marginY' => fn ($v) => 'margin-top:'.$px($v).';margin-bottom:'.$px($v),
            'gap' => fn ($v) => 'gap:'.$px($v),
            'maxWidth' => fn ($v) => 'max-width:'.($v === 'full' ? '100%' : $px($v)),
            'radius' => fn ($v) => 'border-radius:'.$px($v),
            'align' => fn ($v) => in_array($v, ['left', 'center', 'right'], true) ? 'text-align:'.$v : null,
            'justify' => fn ($v) => 'justify-content:'.$this->flexAlign($v),
            'items' => fn ($v) => 'align-items:'.$this->flexAlign($v),
            'bg' => fn ($v) => 'background:'.$this->color($v),
            'color' => fn ($v) => 'color:'.$this->color($v),
            'shadow' => fn ($v) => ($s = $this->shadow($v)) ? 'box-shadow:'.$s : null,
            'minHeight' => fn ($v) => 'min-height:'.$px($v),
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
                $vars[] = '--pp-'.$this->slug($name).':'.$hex;
            }
        }
        $font = $globals['typography']['font'] ?? null;
        if (is_string($font) && $font !== '') {
            $vars[] = "--pp-font:'".addslashes($font)."', ui-sans-serif, system-ui, sans-serif";
        }
        $radius = $globals['radius']['card'] ?? null;
        if ($radius !== null) {
            $vars[] = '--pp-radius:'.(is_numeric($radius) ? "{$radius}px" : $radius);
        }

        // Button corner radius derived from the chosen button shape.
        $vars[] = '--pp-btn-radius:'.match ($globals['buttons']['radius'] ?? 'rounded') {
            'pill' => '9999px',
            'square' => '2px',
            default => '12px',
        };

        // Convenience alias so ported legacy markup can keep using --pp-accent.
        if (isset($globals['colors']['brand'])) {
            $vars[] = '--pp-accent:'.$globals['colors']['brand'];
        }

        return $vars === [] ? '' : ':root{'.implode(';', $vars).'}';
    }

    /**
     * @param  list<string>  $base
     * @param  list<string>  $tablet
     * @param  list<string>  $mobile
     */
    private function walk(BuilderNode $node, array &$base, array &$tablet, array &$mobile): void
    {
        $sel = '#'.$node->id;

        if (($b = $this->rules($node->style['base'] ?? [])) !== '') {
            $base[] = "{$sel}{{$b}}";
        }
        if (($t = $this->rules($node->style['tablet'] ?? [])) !== '') {
            $tablet[] = "{$sel}{{$t}}";
        }
        if (($m = $this->rules($node->style['mobile'] ?? [])) !== '') {
            $mobile[] = "{$sel}{{$m}}";
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
        $out = [];
        foreach ($style as $key => $value) {
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

    /** Resolve a colour value: {token:'brand'} → var(--pp-brand); else raw. */
    private function color(mixed $value): string
    {
        if (is_array($value) && isset($value['token'])) {
            return 'var(--pp-'.$this->slug((string) $value['token']).')';
        }

        return is_string($value) ? $value : 'transparent';
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
            'none', false, null => null,
            default => is_string($v) ? $v : null,
        };
    }

    private function slug(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? $name;
    }
}
