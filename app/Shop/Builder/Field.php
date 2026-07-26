<?php

declare(strict_types=1);

namespace App\Shop\Builder;

/**
 * Terse builder for property-panel field definitions. A field is just an array;
 * these static helpers keep block `schema()` methods readable and consistent, and
 * carry enough metadata for the panel UI, defaults, and validation to be generated.
 */
final class Field
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function make(string $type, string $key, string $label, mixed $default, array $extra = []): array
    {
        return array_merge([
            'type' => $type,
            'key' => $key,
            'label' => $label,
            'default' => $default,
        ], $extra);
    }

    /** @return array<string, mixed> */
    public static function text(string $key, string $label, string $default = '', ?int $max = 200): array
    {
        return self::make('text', $key, $label, $default, ['max' => $max]);
    }

    /** @return array<string, mixed> */
    public static function textarea(string $key, string $label, string $default = '', ?int $max = 2000): array
    {
        return self::make('textarea', $key, $label, $default, ['max' => $max]);
    }

    /** @return array<string, mixed> */
    public static function richtext(string $key, string $label, string $default = ''): array
    {
        return self::make('richtext', $key, $label, $default, ['max' => 8000]);
    }

    /** @return array<string, mixed> */
    public static function number(string $key, string $label, int|float $default = 0, ?float $min = null, ?float $max = null): array
    {
        return self::make('number', $key, $label, $default, ['min' => $min, 'max' => $max]);
    }

    /** @return array<string, mixed> */
    public static function toggle(string $key, string $label, bool $default = false): array
    {
        return self::make('toggle', $key, $label, $default);
    }

    /**
     * @param  array<string, string>  $options  value => label
     * @return array<string, mixed>
     */
    public static function select(string $key, string $label, array $options, string $default = ''): array
    {
        return self::make('select', $key, $label, $default !== '' ? $default : array_key_first($options), ['options' => $options]);
    }

    /** @return array<string, mixed> */
    public static function link(string $key, string $label, string $default = ''): array
    {
        return self::make('link', $key, $label, $default, ['max' => 500]);
    }

    /** @return array<string, mixed> */
    public static function image(string $key, string $label, string $default = ''): array
    {
        return self::make('image', $key, $label, $default, ['max' => 500]);
    }

    /** @return array<string, mixed> */
    public static function icon(string $key, string $label, string $default = 'check'): array
    {
        return self::make('icon', $key, $label, $default);
    }

    /**
     * The section's layout/style variant — a select rendered as a prominent
     * "Layout" picker at the top of the panel. The block partial switches its whole
     * markup on `props.variant`, so a variant is a real layout, not just a colour.
     *
     * @param  array<string, string>  $options  value => label
     * @return array<string, mixed>
     */
    public static function variant(array $options, string $default = ''): array
    {
        return self::make('variant', 'variant', 'Layout / style', $default !== '' ? $default : array_key_first($options), ['options' => $options]);
    }

    /** A design-token colour reference or raw hex. @return array<string, mixed> */
    public static function color(string $key, string $label, string $default = ''): array
    {
        return self::make('color', $key, $label, $default);
    }

    /**
     * A repeatable list of sub-fields. `$item` is the field set for one row.
     *
     * @param  list<array<string, mixed>>  $item
     * @param  list<array<string, mixed>>  $default  starting rows
     * @return array<string, mixed>
     */
    public static function repeater(string $key, string $label, array $item, array $default = []): array
    {
        return self::make('repeater', $key, $label, $default, ['item' => $item, 'max' => 40]);
    }

    /**
     * Default props derived from a schema's field tree (walks every tab).
     *
     * @param  array<string, list<array<string, mixed>>>  $schema
     * @return array<string, mixed>
     */
    public static function defaultsFrom(array $schema): array
    {
        $out = [];
        foreach ($schema as $fields) {
            foreach ($fields as $field) {
                $out[$field['key']] = $field['default'] ?? null;
            }
        }

        return $out;
    }
}
