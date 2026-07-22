<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class BroadcastAttempt extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject_type', 'subject_id', 'tx_hash', 'attempt', 'outcome', 'provider_response',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'provider_response' => 'array',
        ];
    }
}
