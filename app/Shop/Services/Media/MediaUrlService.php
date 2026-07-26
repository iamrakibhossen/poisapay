<?php

declare(strict_types=1);

namespace App\Shop\Services\Media;

use App\Shop\Models\ShopMedia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Render-time URL work: resolve a stored image URL back to its {@see ShopMedia}
 * record and emit responsive `srcset` + WebP (via <picture>) with intrinsic
 * dimensions. A legacy/external URL that isn't a library asset resolves to null and
 * renders as a plain lazy <img> — exactly how existing pages already behave.
 */
final class MediaUrlService
{
    /** @var array<string, ShopMedia|null> in-request memo on top of the cache. */
    private array $memo = [];

    public function resolve(?string $url): ?ShopMedia
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = strtok(trim($url), '#');
        $url = strtok($url, '?') ?: $url;

        if (array_key_exists($url, $this->memo)) {
            return $this->memo[$url];
        }

        $path = $this->pathFor($url);
        if ($path === null) {
            return $this->memo[$url] = null;
        }

        $id = Cache::remember(
            'media:resolve:'.md5($path),
            (int) config('media.resolve_cache_ttl', 86400),
            fn () => ShopMedia::where('path', $path)->value('id'),
        );

        return $this->memo[$url] = $id ? ShopMedia::find($id) : null;
    }

    /**
     * A ready-to-render responsive image element for a stored URL. Falls back to a
     * plain lazy <img> for external/legacy URLs.
     *
     * @param  array<string, string|null>  $attrs  HTML attributes for the <img>
     */
    public function img(?string $url, array $attrs = [], string $sizes = '100vw'): HtmlString
    {
        $media = $this->resolve($url);
        $attrs['loading'] ??= 'lazy';
        $attrs['decoding'] ??= 'async';

        if (! $media instanceof ShopMedia) {
            $attrs['src'] = $url ?? '';

            return new HtmlString('<img'.$this->attrs($attrs).'>');
        }

        $attrs['alt'] ??= $media->alt ?? '';
        if ($media->width && $media->height && ! isset($attrs['width'])) {
            $attrs['width'] = (string) $media->width;
            $attrs['height'] = (string) $media->height;
        }
        $attrs['src'] = $media->url();

        $native = $this->srcset($media, false);
        $webp = $this->srcset($media, true);

        if ($native === '' && $webp === '') {
            return new HtmlString('<img'.$this->attrs($attrs).'>');
        }

        if ($native !== '') {
            $attrs['srcset'] = $native;
            $attrs['sizes'] = $sizes;
        }

        $img = '<img'.$this->attrs($attrs).'>';
        if ($webp === '') {
            return new HtmlString($img);
        }

        // display:contents keeps the <picture> layout-neutral so the <img>'s own
        // sizing classes behave exactly as before (no layout regression).
        return new HtmlString(
            '<picture style="display:contents"><source type="image/webp" srcset="'.e($webp).'" sizes="'.e($sizes).'">'.$img.'</picture>'
        );
    }

    public function forget(ShopMedia $media): void
    {
        Cache::forget('media:resolve:'.md5($media->path));
        unset($this->memo[$media->url()]);
    }

    /** Build a `srcset` from a media's variants (webp siblings or native). */
    private function srcset(ShopMedia $media, bool $webp): string
    {
        $disk = ShopMedia::disk();
        $out = [];
        foreach ($media->variants as $key => $v) {
            if (! is_array($v)) {
                continue;
            }
            if (str_ends_with((string) $key, '_webp') !== $webp) {
                continue;
            }
            $width = (int) ($v['width'] ?? 0);
            if ($width > 0 && ! empty($v['path'])) {
                $out[$width] = Storage::disk($disk)->url($v['path']).' '.$width.'w';
            }
        }
        ksort($out);

        return implode(', ', $out);
    }

    private function pathFor(string $url): ?string
    {
        $disk = ShopMedia::disk();
        $base = rtrim((string) Storage::disk($disk)->url(''), '/').'/';
        $path = null;

        if (Str::startsWith($url, $base)) {
            $path = ltrim(Str::after($url, $base), '/');
        } elseif (Str::startsWith($url, '/storage/')) {
            $path = ltrim(Str::after($url, '/storage/'), '/');
        } elseif (Str::startsWith($url, ['http://', 'https://', '//'])) {
            $p = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
            $path = Str::startsWith($p, 'storage/') ? Str::after($p, 'storage/') : $p;
        }

        if ($path === null || $path === '') {
            return null;
        }

        $dir = trim((string) config('media.directory', 'media'), '/');

        return Str::startsWith($path, $dir.'/') ? $path : null;
    }

    /** @param array<string, string|null> $attrs */
    private function attrs(array $attrs): string
    {
        $out = '';
        foreach ($attrs as $k => $v) {
            if (($v === null || $v === '') && $k !== 'alt') {
                continue;
            }
            $out .= ' '.$k.'="'.e((string) $v).'"';
        }

        return $out;
    }
}
