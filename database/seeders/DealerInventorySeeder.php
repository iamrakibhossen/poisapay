<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Support\Money;
use Illuminate\Database\Seeder;

/**
 * Seed house dealer inventory so the exchange has liquidity to fill swaps.
 *
 * Internal swaps trade against dealer inventory (never customer custody); with an
 * empty inventory every swap fails the per-asset solvency gate in
 * {@see \App\Domain\Exchange\ExchangeService::execute}. This mirrors
 * {@see \App\Console\Commands\InjectInventoryCommand}: DR treasury:hot / CR
 * dealer:inventory, funded into the canonical (pooled) asset of each coin — the
 * only asset ids the exchange resolves inventory against.
 *
 * Idempotent: a stable per-asset idempotency key means re-running (or running on
 * an already-funded environment) is a no-op, not a double injection.
 */
class DealerInventorySeeder extends Seeder
{
    /** Target working capital per coin, keyed by symbol (decimal units). */
    private const AMOUNTS = [
        'USDT' => '2000000',
        'USDC' => '2000000',
        'USD' => '2000000',
        'EUR' => '2000000',
        'BDT' => '200000000',
        'TRX' => '2000000',
        'POL' => '2000000',
        'ETH' => '20000',
        'BNB' => '100000',
        'AVAX' => '100000',
        'BTC' => '1000',
    ];

    private const DEFAULT_AMOUNT = '1000000';

    public function run(LedgerService $ledger): void
    {
        $resolver = $ledger->resolver();

        // One representative (canonical = lowest id) asset per coin.
        $canonical = Asset::where('is_active', true)
            ->get()
            ->groupBy('currency_id')
            ->map(fn ($group) => $group->sortBy('id')->first());

        foreach ($canonical as $asset) {
            $amount = self::AMOUNTS[$asset->symbol] ?? self::DEFAULT_AMOUNT;
            $money = Money::ofDecimal($amount, $asset->decimals, $asset->symbol);

            $ledger->post(new EntryData(
                type: 'inventory.inject',
                idempotencyKey: 'inv-seed:'.$asset->id.':'.$money->baseString(),
                lines: [
                    PostingLine::debit($resolver->system(LedgerAccountType::TreasuryHot, $asset->id)->id, $asset->id, $money),
                    PostingLine::credit($resolver->system(LedgerAccountType::TradingInventory, $asset->id)->id, $asset->id, $money),
                ],
                memo: "Seed dealer-inventory liquidity: {$money->format()}",
            ));
        }
    }
}
