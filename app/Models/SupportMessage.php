<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ticket_id
 * @property string|null $author_id
 * @property string|null $author_name
 * @property string $body
 * @property bool $is_staff
 * @property Carbon $created_at
 */
class SupportMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'ticket_id', 'author_id', 'author_name', 'body', 'is_staff',
    ];

    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
