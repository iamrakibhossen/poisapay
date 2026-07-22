<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

use App\Domain\Audit\ActivityLogger;
use App\Enums\KycTier;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Onboard a user as a merchant (TDD §8). One profile per user. Auto-approves
 * when the platform is configured to, otherwise lands in the pending queue for
 * an operator. Fully KYC-gated — only verified accounts can accept payments.
 */
class RegisterMerchantAction
{
    /** @param  array<string, mixed>  $input */
    public function execute(User $user, array $input): Merchant
    {
        if ($user->merchant()->exists()) {
            throw new RuntimeException('This account is already registered as a merchant.');
        }
        if (! feature('merchant_enabled')) {
            throw new RuntimeException('Merchant accounts are currently unavailable.');
        }
        if ($user->tier() !== KycTier::Full) {
            throw new RuntimeException('Full identity verification is required to become a merchant.');
        }

        $autoApprove = (bool) getSetting('merchant_auto_approve', true);

        $merchant = Merchant::create([
            'user_id' => $user->id,
            'business_name' => $input['business_name'],
            'slug' => $this->uniqueSlug($input['business_name']),
            'category' => $input['category'] ?? null,
            'website' => $input['website'] ?? null,
            'support_email' => $input['support_email'] ?? $user->email,
            'statement_descriptor' => Str::of($input['business_name'])->upper()->limit(22, '')->toString(),
            'settlement_asset_id' => $input['settlement_asset_id'] ?? null,
            'status' => $autoApprove ? MerchantStatus::Active : MerchantStatus::Pending,
            'approved_at' => $autoApprove ? now() : null,
        ]);

        ActivityLogger::log('merchant.registered', $merchant, ['auto_approved' => $autoApprove]);
        if (! $autoApprove) {
            notifyAdmins(
                'New merchant application',
                $merchant->business_name.' has applied to accept payments.',
                route('admin.merchants'),
                'merchant',
            );
        }

        return $merchant;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'merchant';
        $slug = $base;
        $i = 1;
        while (Merchant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$i;
        }

        return $slug;
    }
}
