<?php

declare(strict_types=1);

namespace App\Domain\Compliance;

use App\Domain\Compliance\Contracts\ScreeningProvider;
use App\Models\ScreeningResult;
use App\Models\User;

/**
 * Sanctions / PEP screening (TDD §10.2). Delegates the actual decision to a
 * swappable {@see ScreeningProvider} and persists the outcome as a durable
 * ScreeningResult. Callers (TransactionMonitor, onboarding listeners) are
 * unaffected by which provider is bound.
 */
class ScreeningService
{
    public function __construct(private readonly ScreeningProvider $provider) {}

    public function screen(User $user, string $context, ?string $subjectId = null): ScreeningResult
    {
        $outcome = $this->provider->evaluate($user);

        return ScreeningResult::create([
            'user_id' => $user->id,
            'context' => $context,
            'subject_id' => $subjectId,
            'provider' => $this->provider->name(),
            'result' => $outcome->status,
            'score' => $outcome->score,
            'matches' => $outcome->matches ?: null,
        ]);
    }
}
