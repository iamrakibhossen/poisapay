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
use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Enums\P2pPriceType;
use App\Models\Asset;
use App\Models\P2pAd;
use App\Models\P2pMerchantProfile;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Populate the P2P marketplace with a deep roster of merchants running live
 * buy/sell ads (100+), so the browse page looks like a real market. Faker-free
 * (runs under --no-dev) and idempotent per-merchant: a merchant that already has
 * ads is left untouched, so re-running never duplicates and can top up a DB that
 * was seeded before this expansion.
 *
 * The marketplace itself stays behind the `p2p_enabled` flag; this only provides
 * the data an operator sees once they turn it on.
 */
class P2pSeeder extends Seeder
{
    public function run(): void
    {
        $usdt = Asset::where('symbol', 'USDT')->orderBy('id')->first();
        if (! $usdt) {
            return;
        }

        $ledger = app(LedgerService::class);
        $resolver = app(AccountResolver::class);
        $resolver->ensureSystemAccounts($usdt->id);

        // Generous USDT float per merchant so every sell ad is backed (a sell ad
        // requires the poster to hold the advertised inventory).
        $seedUsdtBase = Money::ofDecimal('25000', $usdt->decimals, 'USDT')->baseString();

        foreach ($this->roster() as $i => $m) {
            // Explicit create (not factory) so it runs in --no-dev production where
            // faker is absent; keyed by email for idempotency.
            $user = User::updateOrCreate(
                ['email' => $m['email']],
                [
                    'name' => $m['name'],
                    'phone' => '+88018'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'kyc_tier' => KycTier::Full,
                    'kyc_status' => KycStatus::Approved,
                    'referral_code' => 'P2P'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                    'base_currency' => 'BDT',
                ],
            );

            P2pMerchantProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'is_online' => $i % 4 !== 0,
                    'level' => $m['level'],
                    'badges' => $m['level'] >= 2 ? ['verified'] : [],
                    'trade_count' => $m['trades'],
                    'completed_count' => $m['trades'],
                    'completion_rate_bps' => 9500 + ($i * 13) % 500,
                    'avg_release_seconds' => 60 + ($i * 17) % 240,
                    'avg_pay_seconds' => 240 + ($i * 11) % 180,
                    'total_volume' => (string) ($m['trades'] * 100_000000), // ~trades × 100 USDT (6dp)
                    'rating' => $m['rating'],
                ],
            );

            // Fund the merchant's USDT balance (treasury → user available).
            // Idempotent via the entry's idempotency key.
            $available = $resolver->forUser($user, LedgerAccountType::UserAvailable, $usdt->id);
            $treasury = $resolver->system(LedgerAccountType::TreasuryPending, $usdt->id);
            $ledger->post(new EntryData(
                type: 'seed.credit',
                idempotencyKey: 'p2p-seed:'.$user->id.':'.$usdt->id,
                lines: [
                    PostingLine::debit($treasury->id, $usdt->id, $seedUsdtBase),
                    PostingLine::credit($available->id, $usdt->id, $seedUsdtBase),
                ],
                memo: 'Seed P2P merchant balance',
            ));

            // Per-merchant idempotency: skip ad creation if they already have ads.
            if (P2pAd::where('user_id', $user->id)->exists()) {
                continue;
            }

            foreach ($m['ads'] as $ad) {
                $floating = $ad['price_type'] === P2pPriceType::Floating;
                P2pAd::create([
                    'user_id' => $user->id,
                    'side' => $ad['side'],
                    'asset_id' => $usdt->id,
                    'fiat_currency' => 'BDT',
                    'price_type' => $ad['price_type'],
                    'fixed_price' => $floating ? null : $ad['price'],
                    'margin_bps' => $floating ? $ad['margin'] : null,
                    'min_order' => $ad['min_order'],
                    'max_order' => $ad['max_order'],
                    'available_amount' => $ad['total'],
                    'total_amount' => $ad['total'],
                    'payment_window_min' => $ad['window'],
                    'status' => P2pAdStatus::Active,
                    'priority' => $m['level'],
                ]);
            }
        }
    }

    /**
     * Build the merchant roster: three flagship personas plus a deep generated
     * set, each carrying several ads. Deterministic — no faker, no randomness.
     *
     * @return list<array{name:string, email:string, level:int, rating:string, trades:int, ads:list<array<string,mixed>>}>
     */
    private function roster(): array
    {
        $roster = [];

        // Flagship personas (keep the original three, same emails → idempotent).
        $flagship = [
            ['CryptoBazar BD', 2, '4.95', 1240],
            ['Dhaka Digital', 3, '4.99', 5300],
            ['FastPay Traders', 1, '4.80', 210],
        ];
        foreach ($flagship as $i => [$name, $level, $rating, $trades]) {
            $roster[] = [
                'name' => $name,
                'email' => 'merchant'.($i + 1).'@poisapay.test',
                'level' => $level,
                'rating' => $rating,
                'trades' => $trades,
                'ads' => $this->adsFor($i, 4 + $i % 3),
            ];
        }

        // Generated traders — 25 × ~5 ads ≈ 125 more ads.
        $cities = ['Dhaka', 'Chattogram', 'Sylhet', 'Khulna', 'Rajshahi', 'Barishal', 'Cumilla', 'Bogura', 'Jashore', 'Mymensingh', 'Rangpur', 'Gazipur', 'Narayanganj', 'Tangail', 'Pabna'];
        $suffixes = ['Exchange', 'Traders', 'Digital', 'Crypto', 'Pay', 'Coins', 'Hub', 'Market', 'Capital', 'Cash'];

        for ($n = 1; $n <= 25; $n++) {
            $roster[] = [
                'name' => $cities[$n % count($cities)].' '.$suffixes[$n % count($suffixes)],
                'email' => 'p2ptrader'.$n.'@poisapay.test',
                'level' => 1 + $n % 3,
                'rating' => number_format(4.60 + ($n % 40) / 100, 2, '.', ''),
                'trades' => 80 + $n * 47,
                'ads' => $this->adsFor($n + 3, 4 + $n % 3),
            ];
        }

        return $roster;
    }

    /**
     * Deterministically build a spread of ads for one merchant.
     *
     * @return list<array<string, mixed>>
     */
    private function adsFor(int $seed, int $count): array
    {
        $minOrders = ['500.00', '1000.00', '1500.00', '2000.00'];
        $maxOrders = ['50000.00', '80000.00', '120000.00', '150000.00', '200000.00'];
        $totalsUsdt = ['200', '350', '500', '800', '1200', '1800', '2500'];   // all ≥ 100
        $windows = [15, 30, 45, 60];
        $margins = [40, -30, 80, -50, 60];

        $ads = [];
        for ($k = 0; $k < $count; $k++) {
            $t = $seed * 7 + $k * 3;
            $floating = $t % 3 === 0;
            $ads[] = [
                'side' => ($t % 2 === 0) ? P2pAdType::Sell : P2pAdType::Buy,
                'price_type' => $floating ? P2pPriceType::Floating : P2pPriceType::Fixed,
                'price' => number_format(119.0 + ($t % 9) * 0.5, 4, '.', ''),
                'margin' => $margins[$t % count($margins)],
                'min_order' => $minOrders[$t % count($minOrders)],
                'max_order' => $maxOrders[$t % count($maxOrders)],
                'total' => Money::ofDecimal($totalsUsdt[$t % count($totalsUsdt)], 6, 'USDT')->baseString(),
                'window' => $windows[$t % count($windows)],
            ];
        }

        return $ads;
    }
}
