<?php

declare(strict_types=1);

namespace App\Shop\Jobs;

use App\Shop\Actions\Domain\ProvisionSsl;
use App\Shop\Models\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Provisions the TLS certificate for a verified domain off the request path. The
 * Action owns the retry schedule; unique-per-domain to avoid concurrent orders.
 */
class ProvisionSslJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $domainId) {}

    public function uniqueId(): string
    {
        return $this->domainId;
    }

    public function handle(ProvisionSsl $action): void
    {
        $domain = Domain::find($this->domainId);

        if ($domain !== null) {
            $action->execute($domain);
        }
    }
}
