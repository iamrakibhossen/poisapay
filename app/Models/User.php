<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\KycTier;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password',
        'kyc_tier', 'kyc_status', 'referral_code', 'referred_by',
        'base_currency', 'locale', 'timezone', 'is_frozen',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'anti_phishing_code', 'telegram_chat_id',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'is_frozen' => 'boolean',
            'kyc_tier' => KycTier::class,
            'kyc_status' => KycStatus::class,
            'uid' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Every user gets a public, shareable numeric ID on creation (§F4.1).
        // Retry on the `uid` unique index so a concurrent collision can't slip
        // through the exists() pre-check — the DB is the source of truth.
        static::creating(function (User $user) {
            if (empty($user->uid)) {
                $user->uid = static::generateUid();
            }
        });

        static::saving(function (User $user) {
            if ($user->isDirty('uid')) {
                Validator::make(['uid' => $user->uid], [
                    'uid' => ['required', 'integer', Rule::unique('users')->ignore($user)],
                ])->validate();
            }
        });
    }

    /** A unique 9-digit public account ID others use to send money. */
    public static function generateUid(): int
    {
        do {
            $uid = random_int(100_000_000, 999_999_999);
        } while (static::where('uid', $uid)->exists());

        return $uid;
    }

    // ----- Relationships -----

    public function ledgerAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }

    public function depositAddresses(): HasMany
    {
        return $this->hasMany(DepositAddress::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function sentTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'sender_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function kycProfiles(): HasMany
    {
        return $this->hasMany(KycProfile::class);
    }

    public function latestKyc(): HasOne
    {
        return $this->hasOne(KycProfile::class)->latestOfMany();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** @return HasMany<LoginHistory, $this> */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class)->latest('created_at');
    }

    /** @return HasMany<UserPushToken, $this> */
    public function pushTokens(): HasMany
    {
        return $this->hasMany(UserPushToken::class);
    }

    /** @return HasMany<SecurityEvent, $this> */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class)->latest();
    }

    public function spendingPriority(): HasMany
    {
        return $this->hasMany(UserSpendingPriority::class)->orderBy('position');
    }

    /** @return HasMany<AddressBookEntry, $this> */
    public function addressBook(): HasMany
    {
        return $this->hasMany(AddressBookEntry::class)->orderByDesc('is_favorite')->orderByDesc('last_used_at');
    }

    public function favoriteAssets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'user_favorite_assets')->withPivot('position');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function merchant(): HasOne
    {
        return $this->hasOne(Merchant::class);
    }

    // ----- Domain helpers -----

    public function isMerchant(): bool
    {
        return $this->merchant()->exists();
    }

    public function tier(): KycTier
    {
        return $this->kyc_tier;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }
}
