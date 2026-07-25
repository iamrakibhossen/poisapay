<?php

declare(strict_types=1);

namespace App\Shop\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    use HasUuids;

    protected $table = 'shop_message_attachments';

    protected $fillable = ['message_id', 'disk', 'path', 'original_name', 'size_bytes', 'mime'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
