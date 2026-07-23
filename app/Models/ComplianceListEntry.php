<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A persistent sanctions / watch / whitelist entry (Wave 5).
 *
 * @property string $id
 * @property string $list
 * @property string $kind
 * @property string $value
 * @property string|null $reason
 * @property string|null $source
 * @property string|null $added_by
 * @property Carbon|null $expires_at
 */
class ComplianceListEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'list', 'kind', 'value', 'reason', 'source', 'added_by', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
