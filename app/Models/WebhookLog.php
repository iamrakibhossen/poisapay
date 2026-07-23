<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** A single inbound-webhook request/response, logged by the WebhookLogger middleware. */
class WebhookLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider', 'method', 'url', 'route', 'payload', 'headers',
        'ip', 'hash', 'status', 'response', 'retries', 'resolved',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'headers' => 'array',
            'status' => 'integer',
            'retries' => 'integer',
            'resolved' => 'boolean',
        ];
    }

    /** A 2xx/3xx response counts as successfully handled. */
    public function wasSuccessful(): bool
    {
        return $this->status > 0 && $this->status < 400;
    }
}
