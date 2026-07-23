<?php

declare(strict_types=1);

namespace App\Domain\Compliance\TravelRule\Contracts;

use App\Domain\Compliance\TravelRule\DTO\TravelRuleData;

/**
 * Travel Rule (FATF R.16) provider. The stub records the message locally; a real
 * provider (Notabene, TRISA, Sygna Bridge) transmits it to the beneficiary VASP.
 * Selected via config/providers.php.
 */
interface TravelRuleProvider
{
    public function name(): string;

    /** Submit an originator/beneficiary message; returns the provider reference + status. */
    public function submit(TravelRuleData $data): TravelRuleResult;
}
