<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Record a house liquidity injection: the platform funds its own coins into the
 * hot wallet as tradable dealer working capital.
 *
 * Journal entry:  DR treasury:hot  /  CR dealer:inventory   (at fair value on date)
 *
 * IFRS/GAAP classification — the credit leg (dealer:inventory) is EQUITY, a
 * non-monetary CAPITAL CONTRIBUTION IN KIND (IFRS Conceptual Framework / IAS 32;
 * US GAAP ASC 505-10). It is NOT IAS 2 inventory — the coins themselves are the
 * asset (treasury:hot); dealer:inventory is the residual equity claim on that
 * asset (Assets − Liabilities), i.e. the house's own stake in the pooled wallet.
 * It is NOT a liability and NOT customer funds.
 *
 * (If the coins were already recognised in a separate corporate-holdings asset
 * account, this would instead be an asset-to-asset reclassification — the ledger
 * models no such account today, so the coins enter the books here.)
 *
 * This is the ONLY sanctioned way to grow dealer inventory — customer deposits
 * credit customer liabilities, never inventory. See docs/accounting.md.
 */
class InjectInventoryCommand extends Command
{
    protected $signature = 'poisapay:inject-inventory {asset : Asset ID} {amount : Decimal amount, e.g. 100000} {--ref= : Idempotency reference}';

    protected $description = 'Record a house dealer-inventory injection (DR treasury:hot / CR dealer:inventory)';

    public function handle(LedgerService $ledger): int
    {
        $asset = Asset::find((int) $this->argument('asset'));
        if (! $asset) {
            $this->error('Unknown asset ID.');

            return self::FAILURE;
        }

        $money = Money::ofDecimal((string) $this->argument('amount'), $asset->decimals, $asset->symbol);
        if (! $money->isPositive()) {
            $this->error('Amount must be positive.');

            return self::FAILURE;
        }

        $ref = $this->option('ref') ?: 'inject:'.$asset->id.':'.$money->baseString();
        $resolver = $ledger->resolver();

        $ledger->post(new EntryData(
            type: 'inventory.inject',
            idempotencyKey: 'inv-inject:'.$ref,
            lines: [
                PostingLine::debit($resolver->system(LedgerAccountType::TreasuryHot, $asset->id)->id, $asset->id, $money),
                PostingLine::credit($resolver->system(LedgerAccountType::TradingInventory, $asset->id)->id, $asset->id, $money),
            ],
            memo: "House dealer-inventory injection: {$money->format()}",
        ));

        $this->info("Injected {$money->format()} into {$asset->symbol} dealer inventory (treasury:hot backed).");

        return self::SUCCESS;
    }
}
