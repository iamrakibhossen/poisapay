<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Tamper-evident hash chaining for the audit trail (Wave 4, feature-flagged).
 * Each row is stamped with a monotonic sequence (Postgres sequence), the previous
 * row's hash, and a SHA-256 over its own immutable payload. Editing or deleting
 * any earlier row breaks the linkage, which {@see verify()} reports. Chaining is
 * gap-tolerant (a consumed sequence with no row does not break the chain).
 */
final class AuditChain
{
    public static function enabled(): bool
    {
        return feature('security_audit_hash_chain', (bool) config('poisapay.security.flags.audit_hash_chain', true));
    }

    /** Stamp sequence + prev_hash + hash onto a new (unsaved) audit row. */
    public static function assign(AuditLog $log): void
    {
        if (! self::enabled()) {
            return;
        }

        $sequence = (int) DB::selectOne("SELECT nextval('audit_logs_seq') AS n")->n;
        $last = AuditLog::whereNotNull('sequence')->orderByDesc('sequence')->first();
        $prevHash = $last?->hash;

        $log->sequence = $sequence;
        $log->prev_hash = $prevHash;
        $log->hash = self::payloadHash($log, $sequence, $prevHash);
    }

    /** Deterministic SHA-256 over the row's immutable payload + chain links. */
    public static function payloadHash(AuditLog $log, int $sequence, ?string $prevHash): string
    {
        $payload = json_encode([
            'seq' => $sequence,
            'prev' => $prevHash,
            'action' => $log->action,
            'actor_type' => $log->actor_type,
            'actor_id' => $log->actor_id,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'changes' => $log->changes,
            'ip' => $log->ip_address,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', (string) $payload);
    }

    /**
     * Walk the chain in sequence order and verify per-row integrity + linkage.
     *
     * @return array{ok: bool, count: int, brokenAt: int|null}
     */
    public static function verify(): array
    {
        $ok = true;
        $brokenAt = null;
        $count = 0;
        $prevHash = null;

        AuditLog::whereNotNull('sequence')->orderBy('sequence')
            ->chunk(500, function ($rows) use (&$ok, &$brokenAt, &$count, &$prevHash) {
                foreach ($rows as $row) {
                    $count++;
                    $expected = self::payloadHash($row, (int) $row->sequence, $row->prev_hash);
                    if (($row->hash !== $expected || $row->prev_hash !== $prevHash) && $ok) {
                        $ok = false;
                        $brokenAt = (int) $row->sequence;
                    }
                    $prevHash = $row->hash;
                }
            });

        return ['ok' => $ok, 'count' => $count, 'brokenAt' => $brokenAt];
    }
}
