<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScreeningStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningResult extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'context', 'subject_id', 'provider', 'result', 'score', 'matches',
    ];

    protected function casts(): array
    {
        return [
            'result' => ScreeningStatus::class,
            'score' => 'integer',
            'matches' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
