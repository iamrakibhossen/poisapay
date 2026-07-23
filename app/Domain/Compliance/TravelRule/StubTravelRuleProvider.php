<?php

declare(strict_types=1);

namespace App\Domain\Compliance\TravelRule;

use App\Domain\Compliance\TravelRule\Contracts\TravelRuleProvider;
use App\Domain\Compliance\TravelRule\Contracts\TravelRuleResult;
use App\Domain\Compliance\TravelRule\DTO\TravelRuleData;

/**
 * Default Travel Rule provider. Makes no external call — it records the message
 * locally and returns a deterministic reference so the architecture (schema,
 * threshold, capture points) is exercised end-to-end without a live VASP network.
 */
final class StubTravelRuleProvider implements TravelRuleProvider
{
    public function name(): string
    {
        return 'stub';
    }

    public function submit(TravelRuleData $data): TravelRuleResult
    {
        return new TravelRuleResult(
            status: 'submitted',
            providerRef: 'tr_'.substr(hash('sha256', $data->recordId), 0, 20),
        );
    }
}
