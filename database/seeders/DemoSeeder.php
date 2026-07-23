<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $ledger = app(LedgerService::class);
        $resolver = app(AccountResolver::class);

        // Demo consumer with funded wallets. (Operators live in the `admins`
        // table — see AdminSeeder — not here.)
        $demo = User::updateOrCreate(
            ['email' => 'demo@poisapay.test'],
            [
                'name' => 'Rahim Uddin',
                'phone' => '+8801700000000',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'kyc_tier' => KycTier::Full,
                'kyc_status' => KycStatus::Approved,
                'referral_code' => 'RAHIM123',
                'base_currency' => 'BDT',
            ],
        );

        $balances = [
            'USDT' => '1250000000',        // 1,250 USDT (6dp)
            'ETH' => '850000000000000000', // 0.85 ETH
            'TRX' => '4200000000000000000000', // 4,200 TRX
            'BDT' => '4550000',            // 45,500.00 BDT (2dp)
        ];

        foreach ($balances as $symbol => $base) {
            $asset = Asset::where('symbol', $symbol)->first();
            if (! $asset) {
                continue;
            }
            $resolver->ensureSystemAccounts($asset->id);
            $treasury = $resolver->system(LedgerAccountType::TreasuryPending, $asset->id);
            $available = $resolver->forUser($demo, LedgerAccountType::UserAvailable, $asset->id);

            $ledger->post(new EntryData(
                type: 'seed.credit',
                idempotencyKey: 'seed:'.$demo->id.':'.$asset->id,
                lines: [
                    PostingLine::debit($treasury->id, $asset->id, $base),
                    PostingLine::credit($available->id, $asset->id, $base),
                ],
                memo: 'Seed balance',
            ));
        }

        // A few more consumers for admin lists.
        User::factory()->count(12)->create();
    }
}
