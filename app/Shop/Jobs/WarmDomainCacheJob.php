<?php

declare(strict_types=1);

namespace App\Shop\Jobs;

use App\Shop\Models\Domain;
use App\Shop\Services\Domain\DomainResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Primes the host → sales-page routing cache so the first live request is warm.
 * Best-effort: a miss just falls back to a cold lookup.
 */
class WarmDomainCacheJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly string $domainId) {}

    public function handle(DomainResolver $resolver): void
    {
        $domain = Domain::find($this->domainId);

        if ($domain !== null) {
            $resolver->warm($domain);
        }
    }
}
