<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Providers;

use App\Shop\Tracking\Contracts\TrackingProvider;

/**
 * Shared adapter plumbing: the enabled/primary-id check and JS/HTML escaping. A
 * concrete provider only supplies its metadata, fields, and the browser snippet.
 */
abstract class AbstractTrackingProvider implements TrackingProvider
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function docsUrl(): string;

    abstract public function fields(): array;

    abstract public function headScript(array $config): string;

    public function bodyHtml(array $config): string
    {
        return '';
    }

    public function isConfigured(array $config): bool
    {
        if (empty($config['enabled'])) {
            return false;
        }

        $primary = $this->fields()[0]['key'] ?? 'id';
        $value = trim((string) ($config[$primary] ?? ''));
        if ($value === '') {
            return false;
        }

        $pattern = $this->fields()[0]['pattern'] ?? null;

        return $pattern === null || preg_match('/'.$pattern.'/', $value) === 1;
    }

    /**
     * The primary id, trimmed.
     *
     * @param  array<string, mixed>  $config
     */
    protected function primaryId(array $config): string
    {
        return trim((string) ($config[$this->fields()[0]['key']] ?? ''));
    }

    /** Encode a value for safe inline-JS embedding. */
    protected function js(mixed $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Standard field descriptor.
     *
     * @return array{key: string, label: string, required: bool, pattern: ?string, hint: ?string, secret: bool}
     */
    protected function field(string $key, string $label, bool $required, ?string $pattern = null, ?string $hint = null, bool $secret = false): array
    {
        return compact('key', 'label', 'required', 'pattern', 'hint', 'secret');
    }
}
