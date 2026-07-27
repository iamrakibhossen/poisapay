<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Immutable per-page SEO description. Controllers build one and hand it to the
 * layout; the <x-seo.meta> component renders every tag from it. Defaults
 * come from config/seo.php so a page only overrides what differs. Isolated — holds
 * no business logic.
 *
 * @property-read list<array{name:string,url:string}> $breadcrumbs
 * @property-read list<array<string,mixed>> $schemas
 */
final class SeoData
{
    /**
     * @param  list<string>  $keywords
     * @param  list<array{name:string,url:string}>  $breadcrumbs
     * @param  list<array<string,mixed>>  $schemas  extra JSON-LD graph nodes
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $canonical = null,
        public readonly bool $index = true,
        public readonly ?string $image = null,
        public readonly string $type = 'website',
        public readonly array $keywords = [],
        public readonly ?string $publishedTime = null,
        public readonly ?string $modifiedTime = null,
        public readonly array $breadcrumbs = [],
        public readonly array $schemas = [],
    ) {}

    /** Fluent factory. */
    public static function make(?string $title = null, ?string $description = null): self
    {
        return new self(title: $title, description: $description);
    }

    public function withCanonical(?string $url): self
    {
        return $this->copy(['canonical' => $url]);
    }

    public function noindex(): self
    {
        return $this->copy(['index' => false]);
    }

    public function withImage(?string $image): self
    {
        return $this->copy(['image' => $image]);
    }

    public function withType(string $type): self
    {
        return $this->copy(['type' => $type]);
    }

    /** @param  list<string>  $keywords */
    public function withKeywords(array $keywords): self
    {
        return $this->copy(['keywords' => $keywords]);
    }

    /** @param  list<array{name:string,url:string}>  $crumbs */
    public function withBreadcrumbs(array $crumbs): self
    {
        return $this->copy(['breadcrumbs' => $crumbs]);
    }

    /** @param  array<string,mixed>|list<array<string,mixed>>  $schema */
    public function withSchema(array $schema): self
    {
        $add = array_is_list($schema) ? $schema : [$schema];

        return $this->copy(['schemas' => [...$this->schemas, ...$add]]);
    }

    public function articleTimes(?string $published, ?string $modified = null): self
    {
        return $this->copy(['type' => 'article', 'publishedTime' => $published, 'modifiedTime' => $modified]);
    }

    // ── Resolved accessors (fall back to config) ────────────────────────────

    public function resolvedTitle(): string
    {
        $site = (string) config('seo.site_name', 'PaishaPay');
        if ($this->title === null || $this->title === '') {
            return (string) config('seo.default_title', $site);
        }

        // Avoid "PaishaPay · PaishaPay" — only suffix when the brand isn't present.
        return str_contains($this->title, $site) ? $this->title : "{$this->title} · {$site}";
    }

    public function resolvedDescription(): string
    {
        return (string) ($this->description ?: config('seo.default_description', ''));
    }

    public function resolvedCanonical(): string
    {
        return $this->canonical ?: url()->current();
    }

    public function resolvedImageUrl(): string
    {
        $img = $this->image ?: (string) config('seo.default_image', 'images/logo.svg');

        return str_starts_with($img, 'http') ? $img : asset($img);
    }

    /** @return list<string> */
    public function resolvedKeywords(): array
    {
        return $this->keywords ?: (array) config('seo.keywords', []);
    }

    public function robots(): string
    {
        return $this->index ? 'index,follow,max-image-preview:large' : 'noindex,nofollow';
    }

    /** @param  array<string,mixed>  $changes */
    private function copy(array $changes): self
    {
        return new self(
            title: $changes['title'] ?? $this->title,
            description: $changes['description'] ?? $this->description,
            canonical: $changes['canonical'] ?? $this->canonical,
            index: $changes['index'] ?? $this->index,
            image: $changes['image'] ?? $this->image,
            type: $changes['type'] ?? $this->type,
            keywords: $changes['keywords'] ?? $this->keywords,
            publishedTime: $changes['publishedTime'] ?? $this->publishedTime,
            modifiedTime: $changes['modifiedTime'] ?? $this->modifiedTime,
            breadcrumbs: $changes['breadcrumbs'] ?? $this->breadcrumbs,
            schemas: $changes['schemas'] ?? $this->schemas,
        );
    }
}
