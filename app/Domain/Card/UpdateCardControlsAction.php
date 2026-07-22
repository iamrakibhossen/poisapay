<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Card\CardService;
use App\Domain\Audit\ActivityLogger;
use App\Models\Card;
use Illuminate\Support\Arr;

/**
 * Cardholder self-service spend controls (TDD §F3.2): nickname, channel toggles
 * (online / ATM / contactless), per-tx & daily limits, and geo / MCC locks.
 * Every write is a validated whitelist — nothing here can touch money or tokens.
 * After saving, the controls are pushed to the provider (best-effort).
 */
class UpdateCardControlsAction
{
    public function __construct(private readonly CardService $cards) {}

    /** @param  array<string, mixed>  $input */
    public function execute(Card $card, array $input): Card
    {
        $attributes = Arr::only($input, [
            'nickname', 'online_enabled', 'atm_enabled', 'contactless_enabled',
            'daily_limit', 'per_tx_limit', 'allowed_countries', 'blocked_mccs',
        ]);

        // Normalise geo/MCC lists: uppercase ISO-2 countries, digit-only MCCs, de-duped.
        if (array_key_exists('allowed_countries', $attributes)) {
            $attributes['allowed_countries'] = $this->cleanList($attributes['allowed_countries'], fn ($v) => strtoupper(trim($v))) ?: null;
        }
        if (array_key_exists('blocked_mccs', $attributes)) {
            $attributes['blocked_mccs'] = $this->cleanList($attributes['blocked_mccs'], fn ($v) => preg_replace('/\D/', '', $v)) ?: null;
        }

        $card->update($attributes);
        ActivityLogger::log('card.controls.updated', $card, ['fields' => array_keys($attributes)]);

        $fresh = $card->refresh();
        $this->cards->syncControls($fresh);

        return $fresh;
    }

    /** @return array<int, string> */
    private function cleanList(mixed $value, callable $map): array
    {
        return collect(is_array($value) ? $value : explode(',', (string) $value))
            ->map($map)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
