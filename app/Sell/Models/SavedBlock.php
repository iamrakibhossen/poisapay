<?php

declare(strict_types=1);

namespace App\Sell\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller's reusable saved block subtree — a section or component they can drop
 * into any of their pages. Stored as a serialised BuilderNode.
 *
 * @property array<string, mixed> $node
 */
class SavedBlock extends Model
{
    use HasUuids;

    protected $table = 'sell_saved_blocks';

    protected $fillable = ['seller_id', 'name', 'kind', 'node'];

    protected function casts(): array
    {
        return ['node' => 'array'];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
