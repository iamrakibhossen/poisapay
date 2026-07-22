<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasUuids;

    protected $fillable = [
        'title', 'body', 'segment', 'category', 'channels', 'recipients', 'sent_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'recipients' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sent_by');
    }
}
