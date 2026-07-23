<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Kind of message in an order chat thread. `System` messages are emitted by the
 * engine on state changes; `Receipt`/`Image` carry an attachment.
 */
enum P2pMessageType: string
{
    use HasMeta;

    case Text = 'text';
    case Image = 'image';
    case Receipt = 'receipt';
    case System = 'system';

    public function hasAttachment(): bool
    {
        return in_array($this, [self::Image, self::Receipt], true);
    }
}
