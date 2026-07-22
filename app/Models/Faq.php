<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasUuids;

    protected $fillable = [
        'question', 'answer', 'group', 'show_on_homepage', 'sort_order', 'status',
    ];

    protected function casts(): array
    {
        return [
            'show_on_homepage' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<Faq>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /**
     * @param  Builder<Faq>  $query
     */
    public function scopeOnHomepage(Builder $query): void
    {
        $query->where('show_on_homepage', true);
    }

    /**
     * @param  Builder<Faq>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('question');
    }
}
