<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Contracts;

use App\Domain\Compliance\DTO\ScreeningOutcome;
use App\Domain\Compliance\ScreeningService;
use App\Models\User;

/**
 * Sanctions / PEP / AML screening provider (TDD §10.2). Swappable: the stub
 * screens against admin denylists, a real vendor (ComplyAdvantage, Refinitiv)
 * calls out to its API. {@see ScreeningService} persists
 * whatever this returns as a ScreeningResult.
 */
interface ScreeningProvider
{
    /** Stable provider identifier stored on each ScreeningResult. */
    public function name(): string;

    public function evaluate(User $user): ScreeningOutcome;
}
