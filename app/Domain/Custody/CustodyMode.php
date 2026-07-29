<?php

declare(strict_types=1);

namespace App\Domain\Custody;

use App\Domain\Custody\Exceptions\CustodyMisconfiguredException;

/**
 * The single source of truth for custody mode (live vs simulated).
 *
 * Truth = `config('poisapay.custody_simulated')`, derived from the
 * `POISAPAY_CUSTODY_LIVE` env flag. The admin-tunable `custody_simulated` SETTING
 * is only a mirror for the operator UI — if it ever disagrees with the config,
 * {@see assertConsistent()} fails fast so a split-brain (e.g. config=live but
 * setting=simulated) can never silently produce fake broadcasts.
 */
final class CustodyMode
{
    public static function isSimulated(): bool
    {
        return (bool) config('poisapay.custody_simulated');
    }

    public static function isLive(): bool
    {
        return ! self::isSimulated();
    }

    /**
     * Fail fast if the settings-engine `custody_simulated` flag contradicts the
     * config/env source of truth. A missing setting is fine (config wins).
     */
    public static function assertConsistent(): void
    {
        $raw = getSetting('custody_simulated');
        if ($raw === null || $raw === '') {
            return;
        }

        $settingSimulated = filter_var($raw, FILTER_VALIDATE_BOOL);
        if ($settingSimulated !== self::isSimulated()) {
            throw new CustodyMisconfiguredException(sprintf(
                'Custody mode mismatch: config (POISAPAY_CUSTODY_LIVE) is %s but the "custody_simulated" setting is %s. '
                .'Align them (run `php artisan paishapay:custody-doctor --sync`) before processing withdrawals.',
                self::isLive() ? 'LIVE' : 'SIMULATED',
                $settingSimulated ? 'SIMULATED' : 'LIVE',
            ));
        }
    }

    /** Write the settings-engine flag to match the config source of truth. */
    public static function syncSetting(): void
    {
        updateSetting('custody_simulated', self::isSimulated(), 'custody');
    }
}
