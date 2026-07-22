<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReferralStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasUuids;

    protected $fillable = [
        'referrer_id', 'referee_id', 'code', 'status', 'reward_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReferralStatus::class,
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    public function rewardEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reward_entry_id');
    }
}
