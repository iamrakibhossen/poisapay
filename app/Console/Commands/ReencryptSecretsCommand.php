<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Withdrawal;
use Illuminate\Console\Command;

/**
 * Re-encrypt at-rest secrets after an APP_KEY rotation (Wave 4). Set the new key
 * as APP_KEY and the previous one in APP_PREVIOUS_KEYS, deploy, then run this:
 * every encrypted attribute is decrypted (transparently, using the old key when
 * needed) and re-saved under the new key. Re-encryption is non-deterministic, so
 * saving always rewrites the ciphertext. Idempotent and safe to re-run.
 */
class ReencryptSecretsCommand extends Command
{
    protected $signature = 'poisapay:reencrypt {--chunk=500}';

    protected $description = 'Re-encrypt at-rest secrets under the current APP_KEY (run after key rotation)';

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk');
        $count = 0;

        Withdrawal::whereNotNull('payout_details')->chunkById($chunk, function ($rows) use (&$count) {
            foreach ($rows as $withdrawal) {
                // Reading decrypts (with APP_KEY or APP_PREVIOUS_KEYS); saving re-encrypts.
                $withdrawal->payout_details = $withdrawal->payout_details;
                $withdrawal->save();
                $count++;
            }
        });

        $this->info("Re-encrypted {$count} withdrawal payout record(s).");

        return self::SUCCESS;
    }
}
