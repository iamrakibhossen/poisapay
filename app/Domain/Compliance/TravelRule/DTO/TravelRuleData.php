<?php

declare(strict_types=1);

namespace App\Domain\Compliance\TravelRule\DTO;

/**
 * Neutral Travel Rule (FATF R.16) message: originator + beneficiary details for a
 * VASP-to-VASP transfer above the threshold. A real provider (Notabene, TRISA,
 * Sygna) submits this to the counterparty VASP; the stub records it locally.
 */
final class TravelRuleData
{
    public function __construct(
        public readonly string $recordId,
        public readonly string $direction,   // in | out
        public readonly string $asset,
        public readonly string $amount,      // base units
        public readonly string $originatorName,
        public readonly ?string $originatorAccount = null,
        public readonly ?string $beneficiaryAddress = null,
        public readonly ?string $beneficiaryName = null,
    ) {}
}
