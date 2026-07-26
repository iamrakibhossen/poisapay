<?php

declare(strict_types=1);

namespace App\Shop\Jobs;

use App\Shop\Actions\Domain\VerifyDomain;
use App\Shop\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs one DNS verification pass off the request path. The Action owns the retry
 * schedule (re-queues with backoff until the ceiling), so this job only needs a
 * couple of tries for transient infra failures. Unique-per-domain while queued so
 * a burst of "verify again" clicks collapses to one in-flight check.
 */
class VerifyDomainJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $domainId) {}

    public function uniqueId(): string
    {
        return $this->domainId;
    }

    public function handle(VerifyDomain $action): void
    {
        $domain = Domain::find($this->domainId);

        if ($domain !== null) {
            $action->execute($domain);
        }
    }
}
