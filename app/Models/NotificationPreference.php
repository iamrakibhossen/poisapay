<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'category', 'in_app', 'email', 'sms', 'push', 'whatsapp', 'telegram',
    ];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'email' => 'boolean',
            'sms' => 'boolean',
            'push' => 'boolean',
            'whatsapp' => 'boolean',
            'telegram' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
