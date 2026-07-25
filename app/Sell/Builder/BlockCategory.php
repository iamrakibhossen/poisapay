<?php

declare(strict_types=1);

namespace App\Sell\Builder;

/**
 * Insert-palette grouping for block types. Drives the "Add block" panel in the
 * editor — each block declares one category.
 */
enum BlockCategory: string
{
    case Layout = 'layout';
    case Content = 'content';
    case Media = 'media';
    case SocialProof = 'social-proof';
    case Urgency = 'urgency';
    case Commerce = 'commerce';
    case Form = 'form';

    public function label(): string
    {
        return match ($this) {
            self::Layout => 'Layout',
            self::Content => 'Content',
            self::Media => 'Media',
            self::SocialProof => 'Social proof',
            self::Urgency => 'Urgency',
            self::Commerce => 'Commerce',
            self::Form => 'Forms',
        };
    }

    /** Display order of the palette groups. */
    public function order(): int
    {
        return match ($this) {
            self::Layout => 0,
            self::Content => 1,
            self::Media => 2,
            self::Commerce => 3,
            self::SocialProof => 4,
            self::Urgency => 5,
            self::Form => 6,
        };
    }
}
