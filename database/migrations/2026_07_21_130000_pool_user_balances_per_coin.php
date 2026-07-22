<?php

use App\Enums\LedgerAccountType;
use Brick\Math\BigInteger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pool existing per-chain USER balances into one account per coin.
 *
 * User balances are now keyed by the coin's canonical (lowest-id) network. Any
 * legacy account on a non-canonical network has its balance folded into the
 * canonical account and is zeroed. Treasury/system accounts are untouched — they
 * stay per chain. Historical ledger lines are immutable and remain as posted;
 * reconciliation now sums them per coin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $userTypes = array_map(
            fn (LedgerAccountType $t) => $t->value,
            array_filter(LedgerAccountType::cases(), fn ($t) => $t->isUserAccount()),
        );

        // currency_id → canonical (lowest) asset id, and asset id → currency id.
        $canonical = DB::table('assets')->whereNotNull('currency_id')
            ->selectRaw('currency_id, min(id) as canonical')->groupBy('currency_id')
            ->pluck('canonical', 'currency_id');
        $assetCurrency = DB::table('assets')->pluck('currency_id', 'id');

        $accounts = DB::table('ledger_accounts')
            ->whereIn('type', $userTypes)->whereNotNull('user_id')->get();

        foreach ($accounts as $acc) {
            $currencyId = $assetCurrency[$acc->asset_id] ?? null;
            if (! $currencyId) {
                continue;
            }

            $canonicalAsset = (int) ($canonical[$currencyId] ?? $acc->asset_id);
            if ($canonicalAsset === (int) $acc->asset_id) {
                continue; // already the pooled account
            }

            $targetId = $this->canonicalAccountId($acc, $canonicalAsset);

            $srcBalance = DB::table('account_balances')->where('account_id', $acc->id)->value('balance') ?? '0';
            if (BigInteger::of((string) $srcBalance)->isZero()) {
                continue;
            }

            $tgtBalance = DB::table('account_balances')->where('account_id', $targetId)->value('balance') ?? '0';
            $sum = BigInteger::of((string) $tgtBalance)->plus((string) $srcBalance);

            DB::table('account_balances')->where('account_id', $targetId)
                ->update(['balance' => (string) $sum, 'updated_at' => now()]);
            DB::table('account_balances')->where('account_id', $acc->id)
                ->update(['balance' => '0', 'updated_at' => now()]);
        }
    }

    /** Get or create the canonical (pooled) account for the same user + type. */
    private function canonicalAccountId(object $acc, int $canonicalAsset): string
    {
        $existing = DB::table('ledger_accounts')
            ->where('type', $acc->type)->where('user_id', $acc->user_id)->where('asset_id', $canonicalAsset)
            ->value('id');

        if ($existing) {
            return $existing;
        }

        $id = (string) Str::uuid();
        DB::table('ledger_accounts')->insert([
            'id' => $id,
            'type' => $acc->type,
            'user_id' => $acc->user_id,
            'asset_id' => $canonicalAsset,
            'normal_side' => LedgerAccountType::from($acc->type)->normalSide()->value,
            'label' => LedgerAccountType::from($acc->type)->label(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('account_balances')->insert([
            'account_id' => $id, 'balance' => '0', 'version' => 0, 'updated_at' => now(),
        ]);

        return $id;
    }

    public function down(): void
    {
        // Irreversible: pooled balances cannot be split back to their originating
        // chains without the original per-network history.
    }
};
