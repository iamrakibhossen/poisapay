<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NotificationTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'key', 'locale', 'name', 'category', 'channels', 'subject', 'body', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Resolve an active template for an event key (falling back to English). */
    public static function resolve(string $key, string $locale = 'en'): ?self
    {
        return static::where('key', $key)->where('is_active', true)
            ->where(fn ($q) => $q->where('locale', $locale)->orWhere('locale', 'en'))
            ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale])
            ->first();
    }

    /** Render subject/body by substituting {{token}} placeholders. */
    public function render(array $data): array
    {
        return [
            'subject' => $this->interpolate((string) $this->subject, $data),
            'body' => $this->interpolate($this->body, $data),
        ];
    }

    private function interpolate(string $text, array $data): string
    {
        foreach ($data as $token => $value) {
            $text = str_replace('{{'.$token.'}}', (string) $value, $text);
        }

        // Strip any unresolved tokens so raw {{...}} never reaches a user.
        return trim(Str::of($text)->replaceMatches('/\{\{\s*[\w.]+\s*\}\}/', '')->toString());
    }
}
