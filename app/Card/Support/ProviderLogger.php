<?php

declare(strict_types=1);

namespace App\Card\Support;

use App\Models\CardProviderLog;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Writes card_provider_logs rows; secrets are redacted before persistence. */
class ProviderLogger
{
    private const REDACT = '/(pan|cvv|cvc|secret|password|authorization|admin_token|application_token|api_key|pin)/i';

    /** @param array<string, mixed> $attributes */
    public function record(array $attributes): void
    {
        foreach (['request', 'response'] as $key) {
            if (isset($attributes[$key]) && is_array($attributes[$key])) {
                $attributes[$key] = $this->redact($attributes[$key]);
            }
        }

        try {
            // Savepoint so a failed insert never aborts the caller's transaction.
            DB::transaction(fn () => CardProviderLog::create($attributes));
        } catch (Throwable) {
            //
        }
    }

    /**
     * @param  array<mixed, mixed>  $data
     * @return array<mixed, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_string($key) && preg_match(self::REDACT, $key)) {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }
}
