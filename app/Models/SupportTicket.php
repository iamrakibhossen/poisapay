<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupportTicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $subject
 * @property string $category
 * @property string $priority
 * @property SupportTicketStatus $status
 * @property string|null $assigned_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class SupportTicket extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'subject', 'category', 'priority', 'status', 'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportTicketStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }
}
