<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable snapshot of a sales page's builder document — one per publish and
 * per periodic autosave checkpoint. Restoring a revision copies its document back
 * into the page's working draft.
 *
 * @property array<string, mixed> $document
 */
class PageRevision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null; // append-only; created_at only

    protected $table = 'shop_page_revisions';

    protected $fillable = ['sales_page_id', 'author_user_id', 'version', 'label', 'document'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'document' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function salesPage(): BelongsTo
    {
        return $this->belongsTo(SalesPage::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
