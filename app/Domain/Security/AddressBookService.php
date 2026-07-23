<?php

declare(strict_types=1);

namespace App\Domain\Security;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Notification\NotificationService;
use App\Models\AddressBookEntry;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Withdrawal address whitelist + cooldown (Wave 4). A newly added address enters
 * a cooldown window (config-driven) during which it cannot be used for a cash-out;
 * once matured it becomes an active whitelist entry. Enforcement is feature-gated
 * so existing behaviour is unchanged until an operator turns the whitelist on.
 */
class AddressBookService
{
    public function __construct(private readonly NotificationService $notify) {}

    public function cooldownEnabled(): bool
    {
        return feature('security_address_cooldown', (bool) config('poisapay.security.flags.address_cooldown', true));
    }

    public function whitelistEnforced(): bool
    {
        return feature('security_withdrawal_whitelist', (bool) config('poisapay.security.flags.withdrawal_whitelist', false));
    }

    public function cooldownHours(): int
    {
        return (int) getSetting('security_address_cooldown_hours', config('poisapay.security.address_cooldown_hours', 24));
    }

    /** Add (or update) a saved withdrawal address, applying the cooldown policy. */
    public function add(User $user, string $address, ?string $label = null, ?int $chainId = null, ?int $assetId = null): AddressBookEntry
    {
        $cooldown = $this->cooldownEnabled();
        $until = $cooldown ? now()->addHours($this->cooldownHours()) : null;

        $entry = AddressBookEntry::updateOrCreate(
            ['user_id' => $user->id, 'address' => $address, 'chain_id' => $chainId],
            [
                'label' => $label ?: mb_substr($address, 0, 10),
                'asset_id' => $assetId,
                'status' => $cooldown ? 'pending' : 'active',
                'cooldown_until' => $until,
                'whitelisted_at' => $cooldown ? null : now(),
                'blocked_at' => null,
            ],
        );

        SecurityEvent::create([
            'user_id' => $user->id,
            'type' => 'address_added',
            'severity' => 'info',
            'ip_address' => request()->ip(),
            'metadata' => ['address' => $address, 'cooldown_until' => $until?->toIso8601String()],
        ]);

        ActivityLogger::log('security.address.added', $entry, ['address' => $address], actor: $user);

        $this->notify->send($user, 'security.address_added', [
            'title' => 'Withdrawal address added',
            'body' => $cooldown
                ? "A new withdrawal address was added and will be usable in {$this->cooldownHours()} hours."
                : 'A new withdrawal address was added to your account.',
        ], category: 'security');

        return $entry;
    }

    /** Flip matured pending addresses to active (idempotent). */
    public function promoteMatured(User $user): void
    {
        AddressBookEntry::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereNotNull('cooldown_until')
            ->where('cooldown_until', '<=', now())
            ->update(['status' => 'active', 'whitelisted_at' => now()]);
    }

    /** Block (revoke) a whitelisted address. */
    public function block(AddressBookEntry $entry): void
    {
        $entry->update(['status' => 'blocked', 'blocked_at' => now()]);
        ActivityLogger::log('security.address.blocked', $entry, ['address' => $entry->address]);
    }

    /**
     * Enforce the whitelist for an on-chain withdrawal destination. No-op unless the
     * whitelist flag is on. Throws a ValidationException when the address is not an
     * active, matured, non-blocked entry.
     */
    public function assertWithdrawable(User $user, string $address, ?int $chainId = null): void
    {
        if (! $this->whitelistEnforced()) {
            return;
        }

        $this->promoteMatured($user);

        $entry = AddressBookEntry::where('user_id', $user->id)
            ->where('address', $address)
            ->when($chainId !== null, fn ($q) => $q->where('chain_id', $chainId))
            ->first();

        if (! $entry || ! $entry->isWhitelisted()) {
            SecurityEvent::create([
                'user_id' => $user->id,
                'type' => 'whitelist_block',
                'severity' => 'warning',
                'ip_address' => request()->ip(),
                'metadata' => ['address' => $address, 'reason' => $entry?->inCooldown() ? 'in_cooldown' : 'not_whitelisted'],
            ]);

            $message = $entry?->inCooldown()
                ? 'This address is still in its security cooldown. Please try again later.'
                : 'Withdrawals are restricted to whitelisted addresses. Add and confirm this address first.';

            throw ValidationException::withMessages(['toAddress' => $message]);
        }
    }
}
