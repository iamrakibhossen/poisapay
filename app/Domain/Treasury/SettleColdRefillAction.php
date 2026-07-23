<?php

declare(strict_types=1);

namespace App\Domain\Treasury;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Tron\TronGridClient;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\ColdRefillRequest;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Settles a cold → hot refill once the operator's offline-signed tx (recorded on the
 * request as `tx_hash`, status `broadcast`) confirms on-chain. Posts treasury:cold →
 * treasury:hot (debit hot / credit cold) ONLY after confirmation, idempotently. A
 * reverted tx sends the request back to `approved` for the operator to retry. Routes
 * TRON vs EVM confirmation.
 */
class SettleColdRefillAction
{
    public function __construct(
        private readonly TronGridClient $tron,
        private readonly BlockchainProvider $evm,
        private readonly LedgerService $ledger,
        private readonly AccountResolver $accounts,
    ) {}

    public function execute(): int
    {
        $settled = 0;

        ColdRefillRequest::where('status', 'broadcast')->whereNotNull('tx_hash')->get()->each(function (ColdRefillRequest $request) use (&$settled) {
            $asset = Asset::find($request->asset_id);
            $chain = $asset?->chain;
            if ($asset === null || $chain === null) {
                return;
            }

            $confirmed = $this->confirm($chain->key, (string) $request->tx_hash, $asset->requiredConfirmations(), $request);
            if ($confirmed !== true) {
                return; // pending, reverted (handled), or unsupported
            }

            DB::transaction(function () use ($request, $asset) {
                $amount = Money::ofBase($request->amount, $asset->decimals, $asset->symbol);

                $hot = $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id);
                $cold = $this->accounts->system(LedgerAccountType::TreasuryCold, $asset->id);

                $entry = $this->ledger->post(new EntryData(
                    type: 'treasury.refill',
                    idempotencyKey: "refill:settle:{$request->id}",
                    lines: [
                        PostingLine::debit($hot->id, $asset->id, $amount),
                        PostingLine::credit($cold->id, $asset->id, $amount),
                    ],
                    memo: "Refill {$asset->symbol} cold → hot",
                    metadata: ['cold_refill_id' => $request->id],
                ));

                $request->update(['status' => 'settled', 'settle_entry_id' => $entry->id]);
            });

            $settled++;
        });

        return $settled;
    }

    /** true = confirmed; false = reverted (request reset to approved); null = still pending / unsupported. */
    private function confirm(ChainType $chainType, string $txHash, int $required, ColdRefillRequest $request): ?bool
    {
        if ($chainType === ChainType::Tron) {
            $info = $this->tron->transactionInfo($txHash);
            if ($info === null) {
                return null;
            }
            if (! $info['success']) {
                $request->update(['status' => 'approved']); // reverted — operator re-signs

                return false;
            }

            return true;
        }

        if ($chainType->isEvm()) {
            $receipt = $this->evm->getTransactionReceipt($chainType, $txHash);
            if ($receipt === null) {
                return null;
            }
            if (! $receipt['status']) {
                $request->update(['status' => 'approved']);

                return false;
            }

            return $this->evm->blockNumber($chainType) - $receipt['blockNumber'] + 1 >= $required ? true : null;
        }

        return null;
    }
}
