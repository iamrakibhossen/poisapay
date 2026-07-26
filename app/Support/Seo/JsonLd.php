<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * schema.org JSON-LD builders. Each returns a plain array node (no @context — the
 * meta component wraps the collected nodes in a single @graph document). Pure,
 * config-driven, isolated from business logic.
 */
final class JsonLd
{
    /** Stable @id for the sitewide Organization node (referenced by other nodes). */
    public static function orgId(): string
    {
        return rtrim((string) config('app.url'), '/').'/#organization';
    }

    public static function websiteId(): string
    {
        return rtrim((string) config('app.url'), '/').'/#website';
    }

    /** @return array<string,mixed> */
    public static function organization(): array
    {
        $org = (array) config('seo.organization', []);
        $logo = (string) ($org['logo'] ?? 'images/logo.svg');

        return array_filter([
            '@type' => 'Organization',
            '@id' => self::orgId(),
            'name' => $org['name'] ?? config('seo.site_name'),
            'legalName' => $org['legal_name'] ?? null,
            'url' => rtrim((string) config('app.url'), '/').'/',
            'logo' => str_starts_with($logo, 'http') ? $logo : asset($logo),
            'email' => $org['email'] ?? null,
            'sameAs' => $org['same_as'] ?? [],
        ], static fn ($v) => $v !== null && $v !== []);
    }

    /** @return array<string,mixed> */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::websiteId(),
            'name' => config('seo.site_name'),
            'url' => rtrim((string) config('app.url'), '/').'/',
            'publisher' => ['@id' => self::orgId()],
            'inLanguage' => str_replace('_', '-', (string) config('seo.locale', 'en_US')),
        ];
    }

    /** @return array<string,mixed> */
    public static function webPage(string $url, string $name, ?string $description = null, string $type = 'WebPage'): array
    {
        return array_filter([
            '@type' => $type,
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $name,
            'description' => $description,
            'isPartOf' => ['@id' => self::websiteId()],
            'inLanguage' => str_replace('_', '-', (string) config('seo.locale', 'en_US')),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  list<array{name:string,url:string}>  $items
     * @return array<string,mixed>
     */
    public static function breadcrumb(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(static fn (array $it, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $it['name'],
                'item' => $it['url'],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * @param  list<array{question:string,answer:string}>  $qas
     * @return array<string,mixed>
     */
    public static function faq(array $qas): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $qa) => [
                '@type' => 'Question',
                'name' => $qa['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($qa['answer'])],
            ], $qas),
        ];
    }

    /** @return array<string,mixed> */
    public static function softwareApplication(): array
    {
        return [
            '@type' => 'SoftwareApplication',
            'name' => config('seo.site_name'),
            'applicationCategory' => 'FinanceApplication',
            'operatingSystem' => 'Web, iOS, Android',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
            'publisher' => ['@id' => self::orgId()],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public static function product(array $data): array
    {
        return array_filter([
            '@type' => 'Product',
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'image' => $data['image'] ?? null,
            'brand' => ['@type' => 'Brand', 'name' => config('seo.site_name')],
            'offers' => isset($data['price']) ? array_filter([
                '@type' => 'Offer',
                'price' => (string) $data['price'],
                'priceCurrency' => $data['currency'] ?? 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => $data['url'] ?? null,
            ], static fn ($v) => $v !== null) : null,
            'aggregateRating' => isset($data['rating_value'], $data['rating_count']) ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $data['rating_value'],
                'reviewCount' => (int) $data['rating_count'],
            ] : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<string,mixed> */
    public static function contactPage(string $url): array
    {
        return array_merge(self::webPage($url, 'Contact', null, 'ContactPage'), []);
    }

    /**
     * Wrap collected nodes into a single @graph document string for a <script>.
     *
     * @param  list<array<string,mixed>>  $nodes
     */
    public static function graph(array $nodes): string
    {
        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }
}
