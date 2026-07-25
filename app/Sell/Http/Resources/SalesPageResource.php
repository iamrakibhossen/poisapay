<?php

declare(strict_types=1);

namespace App\Sell\Http\Resources;

use App\Sell\Models\SalesPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesPage
 */
class SalesPageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'sections' => $this->sections,
            'theme' => $this->theme,
            'seo' => $this->seo,
            'tracking' => $this->tracking,
            'version' => (int) $this->version,
            'public_url' => "/p/{$this->slug}",
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
