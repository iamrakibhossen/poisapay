<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\Eip1559Transaction;
use App\Domain\Chain\Evm\GasEstimationService;
use App\Domain\Chain\Evm\HotWalletManager;
use App\Domain\Chain\Evm\NonceManager;
use App\Domain\Chain\Tron\TronGridClient;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Domain\Custody\CustodyReadiness;
use App\Domain\Withdrawal\Exceptions\InvalidWithdrawalAddressException;
use App\Domain\Withdrawal\WithdrawalAddressValidator;
use App\Enums\AssetKind;
use App\Enums\ChainType;
use App\Enums\OnchainTxStatus;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\OnchainTx;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Admin treasury sweep of the remaining NATIVE gas balance (TRX / ETH / BNB) out
 * of a platform hot wallet to an external treasury address. Built for the wind-down:
 * it reuses the exact custody primitives the user/revenue withdrawal signers use
 * (SignerKeyProvider, Secp256k1Signer, Eip1559Transaction, NonceManager,
 * GasEstimationService, BlockchainProvider, TronGridClient) rather than duplicating
 * any signing/broadcast logic — it only adds the native value-transfer that the
 * token signers don't cover.
 *
 * This is an operator/CLI action (not a user withdrawal): there is no user ledger
 * liability behind the gas float — it lives off-ledger in the GasWallet balance
 * (synced from chain), so the movement is recorded via OnchainTx + the audit trail
 * and the GasWallet is re-synced afterwards. No double-entry ledger posting is made
 * on purpose: the native gas pile is not a customer liability and posting it against
 * treasury:hot would distort reserve solvency.
 */
class WithdrawGasCommand extends Command
{
    protected $signature = 'paishapay:withdraw-gas
        {--chain= : tron|ethereum|bsc}
        {--to= : Destination (external treasury) address}
        {--amount= : Native amount to send (decimal, e.g. 0.25). Omit to sweep the max spendable balance}
        {--admin= : Super-admin email/username/id to attribute the action to (optional)}
        {--wait=90 : Seconds to wait for on-chain confirmation before returning}
        {--dry-run : Show balance, fee and max withdrawable without broadcasting}';

    protected $description = 'Admin: withdraw remaining native gas (TRX/ETH/BNB) from a hot wallet to an external treasury address';

    /** EVM native value transfer is a fixed 21000 gas. */
    private const EVM_NATIVE_GAS = '21000';

    /** TRON gives no fee estimate for a plain TRX transfer; reserve 1.1 TRX for bandwidth burn on a max sweep. */
    private const TRON_FEE_RESERVE_SUN = '1100000';

    public function __construct(
        private readonly BlockchainProvider $evm,
        private readonly TronGridClient $tron,
        private readonly SignerKeyProvider $keys,
        private readonly Secp256k1Signer $signer,
        private readonly NonceManager $nonces,
        private readonly GasEstimationService $gas,
        private readonly CustodyReadiness $readiness,
        private readonly WithdrawalAddressValidator $addressValidator,
        private readonly HotWalletManager $hotWallets,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! feature('gas_withdrawal_enabled', false)) {
            $this->error('Gas withdrawal is disabled. Enable the "gas_withdrawal_enabled" feature flag first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // 1. Resolve + authorise the acting admin (optional; CLI access is the root authority).
        $admin = null;
        if ($id = $this->option('admin')) {
            $admin = Admin::where('email', $id)->orWhere('username', $id)->orWhere('id', $id)->first();
            if (! $admin) {
                $this->error("No admin found for “{$id}”.");

                return self::FAILURE;
            }
            if (! $admin->hasRole('super-admin')) {
                $this->error("“{$admin->name}” is not a super-admin — this command requires super-admin authority.");

                return self::FAILURE;
            }
        }

        // 2. Resolve the chain + its native asset.
        $chainMap = ['tron' => ChainType::Tron, 'ethereum' => ChainType::Ethereum, 'bsc' => ChainType::Bsc];
        $chainKey = strtolower((string) $this->option('chain'));
        if (! isset($chainMap[$chainKey])) {
            $this->error('Invalid --chain. Supported: tron, ethereum, bsc.');

            return self::FAILURE;
        }
        $chainType = $chainMap[$chainKey];

        $chain = Chain::where('key', $chainType->value)->first();
        if (! $chain) {
            $this->error("No chain row is configured for {$chainType->label()}.");

            return self::FAILURE;
        }

        $asset = Asset::where('chain_id', $chain->id)
            ->whereNull('contract_address')
            ->where('kind', AssetKind::Crypto->value)
            ->first();
        if (! $asset) {
            $this->error("No native asset row found for {$chainType->label()}.");

            return self::FAILURE;
        }
        $asset->setRelation('chain', $chain);

        // 3. Validate the destination against the network (rejects cross-network mistakes).
        $to = trim((string) $this->option('to'));
        try {
            $this->addressValidator->validate($asset, $to);
        } catch (InvalidWithdrawalAddressException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // 4. Readiness: live mode + signer/hot wallet + funded gas + reachable RPC.
        //    Hard-required to broadcast; on a dry-run we only warn so a preview still works.
        $problems = $this->readiness->check($chainType);
        if ($problems !== []) {
            if ($dryRun) {
                foreach ($problems as $p) {
                    $this->warn('• '.$p);
                }
            } else {
                $this->error('Custody is not ready to broadcast:');
                foreach ($problems as $p) {
                    $this->error('  • '.$p);
                }

                return self::FAILURE;
            }
        }

        // 5. Read the hot wallet + on-chain native balance.
        try {
            $hot = $this->keys->hotWalletAddress($chainType);
        } catch (Throwable $e) {
            $this->error('Hot wallet/signer unavailable: '.$e->getMessage());

            return self::FAILURE;
        }

        try {
            $balanceBase = $chainType === ChainType::Tron
                ? $this->tron->accountTrxBalance($hot)
                : $this->evm->getBalance($chainType, $hot);
        } catch (Throwable $e) {
            $this->error('Could not read the hot wallet balance: '.$e->getMessage());

            return self::FAILURE;
        }

        if (bccomp($balanceBase, '0') <= 0) {
            $this->error("The {$chainType->label()} hot wallet ({$hot}) holds no {$asset->symbol}.");

            return self::FAILURE;
        }

        // 6. Estimate the network fee and derive the maximum spendable amount.
        //    Capture the EVM gas params ONCE so the broadcast uses the same numbers the
        //    max-amount was sized against (avoids a value+fee > balance race).
        $gasParams = null;
        if ($chainType->isEvm()) {
            $gasParams = $this->gas->suggest($chainType);
            $feeBase = bcmul($gasParams['maxFeePerGas'], self::EVM_NATIVE_GAS);
        } else {
            $feeBase = self::TRON_FEE_RESERVE_SUN;
        }

        $maxBase = bcsub($balanceBase, $feeBase);
        if (bccomp($maxBase, '0') <= 0) {
            $this->error(sprintf(
                'Balance %s is too low to cover the estimated network fee %s.',
                $asset->money($balanceBase)->format(),
                $asset->money($feeBase)->format(),
            ));

            return self::FAILURE;
        }

        // 7. Resolve the amount to send.
        $amountOpt = $this->option('amount');
        if ($amountOpt !== null && $amountOpt !== '') {
            try {
                $amountBase = Money::ofDecimal((string) $amountOpt, $asset->decimals, $asset->symbol)->baseString();
            } catch (Throwable $e) {
                $this->error("Invalid --amount “{$amountOpt}”.");

                return self::FAILURE;
            }
            if (bccomp($amountBase, '0') <= 0) {
                $this->error('--amount must be greater than zero.');

                return self::FAILURE;
            }
            if (bccomp($amountBase, $maxBase) > 0) {
                $this->error(sprintf(
                    'Requested %s exceeds the max spendable %s (balance %s − fee %s).',
                    $asset->money($amountBase)->format(),
                    $asset->money($maxBase)->format(),
                    $asset->money($balanceBase)->format(),
                    $asset->money($feeBase)->format(),
                ));

                return self::FAILURE;
            }
        } else {
            $amountBase = $maxBase; // sweep everything spendable
        }

        // 8. Summary (dry-run stops here).
        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Chain', $chainType->label()." ({$chainType->value})"],
            ['Asset', $asset->symbol],
            ['Source (hot wallet)', $hot],
            ['Destination', $to],
            ['Current balance', $asset->money($balanceBase)->format()],
            ['Estimated network fee', $asset->money($feeBase)->format()],
            ['Max withdrawable', $asset->money($maxBase)->format()],
            ['Amount to send', $asset->money($amountBase)->format()],
            ['Acting admin', $admin !== null ? $admin->name : 'CLI operator (system)'],
        ]);

        if ($dryRun) {
            $this->info('Dry run — nothing was broadcast.');

            return self::SUCCESS;
        }

        // 9. Typed confirmation before touching the network.
        $this->newLine();
        $this->warn('This will broadcast a REAL on-chain transaction and cannot be undone.');
        if ($this->ask('Type CONFIRM to proceed') !== 'CONFIRM') {
            $this->info('Aborted — no transaction was broadcast.');

            return self::SUCCESS;
        }

        // 10. Sign + broadcast (reuses the custody primitives).
        try {
            $txHash = $chainType === ChainType::Tron
                ? $this->broadcastTron($chainType, $hot, $to, $amountBase)
                : $this->broadcastEvm($chainType, $hot, $to, $amountBase, $gasParams);
        } catch (Throwable $e) {
            $this->error('Broadcast failed: '.$e->getMessage());

            return self::FAILURE;
        }

        // 11. Record the on-chain tx + audit trail.
        $onchain = DB::transaction(function () use ($chain, $asset, $txHash, $hot, $to, $amountBase, $feeBase, $chainType, $admin) {
            $tx = OnchainTx::create([
                'chain_id' => $chain->id,
                'tx_hash' => strtolower($txHash),
                'log_index' => 0,
                'from_address' => $hot,
                'to_address' => $to,
                'asset_id' => $asset->id,
                'amount' => $amountBase,
                'confirmations' => 0,
                'status' => OnchainTxStatus::Detected,
                'direction' => 'out',
            ]);

            ActivityLogger::log('gas.withdrawal', $tx, [
                'chain' => $chainType->value,
                'asset' => $asset->symbol,
                'source' => $hot,
                'destination' => $to,
                'amount' => $amountBase,
                'amount_display' => $asset->money($amountBase)->format(),
                'network_fee' => $feeBase,
                'tx_hash' => strtolower($txHash),
            ], "Admin gas withdrawal: {$asset->money($amountBase)->format()} → {$to}", actor: $admin);

            return $tx;
        });

        $this->newLine();
        $this->info('Broadcast: '.$txHash);
        if ($url = $chain->explorerTxUrl($txHash)) {
            $this->line($url);
        }

        // 12. Wait for confirmation (best-effort), then re-sync the gas wallet balance.
        $this->awaitConfirmation($chainType, $onchain, (int) $this->option('wait'));
        $this->hotWallets->syncGas($chainType);

        return self::SUCCESS;
    }

    /**
     * @param  array{maxPriorityFeePerGas: string, maxFeePerGas: string, gasLimit: string}|null  $gasParams
     */
    private function broadcastEvm(ChainType $chainType, string $hot, string $to, string $amountBase, ?array $gasParams): string
    {
        $gasParams ??= $this->gas->suggest($chainType);
        $privateKey = $this->keys->hotWalletPrivateKey($chainType);
        $nonce = $this->nonces->next($chainType, $hot);
        $chainId = (int) config("poisapay.custody.{$chainType->value}.chain_id");

        $tx = new Eip1559Transaction(
            chainId: $chainId,
            nonce: (string) $nonce,
            maxPriorityFeePerGas: $gasParams['maxPriorityFeePerGas'],
            maxFeePerGas: $gasParams['maxFeePerGas'],
            gasLimit: self::EVM_NATIVE_GAS,
            to: $to,
            value: $amountBase,
            data: '0x',
        );

        $signature = $this->signer->sign($tx->signingHash(), $privateKey);
        $raw = $tx->serialize(
            substr($signature, 0, 64),
            substr($signature, 64, 64),
            (int) hexdec(substr($signature, 128, 2)),
        );

        $txHash = $this->evm->sendRawTransaction($chainType, $raw);
        if (! str_starts_with($txHash, '0x')) {
            throw new RuntimeException('Broadcast returned no transaction hash.');
        }

        return strtolower($txHash);
    }

    private function broadcastTron(ChainType $chainType, string $hot, string $to, string $amountBase): string
    {
        $built = $this->tron->createTrxTransfer($hot, $to, $amountBase);
        $txId = $built['txID'] ?? null;
        if (! $txId) {
            throw new RuntimeException($built['result']['message'] ?? 'Could not build the TRON transfer.');
        }

        $built['signature'] = [$this->signer->sign($txId, $this->keys->hotWalletPrivateKey($chainType))];

        $result = $this->tron->broadcast($built);
        if (! ($result['result'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Broadcast rejected by the network.');
        }

        return (string) $txId;
    }

    /**
     * Poll the same confirmation surface the custody tick jobs use. Best-effort: if it
     * doesn't confirm within the window we leave the OnchainTx as Detected — the tick
     * jobs / a re-run will finalise it.
     */
    private function awaitConfirmation(ChainType $chainType, OnchainTx $onchain, int $seconds): void
    {
        if ($seconds <= 0) {
            $this->line('Not waiting for confirmation (--wait=0). Custody jobs will finalise it.');

            return;
        }

        $this->line("Waiting up to {$seconds}s for confirmation…");
        $deadline = $seconds;
        $step = 6;

        while ($deadline > 0) {
            try {
                if ($chainType === ChainType::Tron) {
                    $info = $this->tron->transactionInfo($onchain->tx_hash);
                    if ($info) {
                        $this->finalise($onchain, $info['success'], $info['blockNumber'], 1);

                        return;
                    }
                } else {
                    $receipt = $this->evm->getTransactionReceipt($chainType, $onchain->tx_hash);
                    if ($receipt) {
                        $head = $this->evm->blockNumber($chainType);
                        $confs = max(1, $head - $receipt['blockNumber'] + 1);
                        $this->finalise($onchain, $receipt['status'], $receipt['blockNumber'], $confs);

                        return;
                    }
                }
            } catch (Throwable $e) {
                // transient RPC hiccup — keep polling until the deadline
            }

            sleep($step);
            $deadline -= $step;
        }

        $this->warn('Still pending after the wait window — custody jobs will confirm it.');
    }

    private function finalise(OnchainTx $onchain, bool $success, int $blockNumber, int $confirmations): void
    {
        $onchain->update([
            'status' => $success ? OnchainTxStatus::Confirmed : OnchainTxStatus::Failed,
            'block_number' => $blockNumber,
            'confirmations' => $confirmations,
        ]);

        $success
            ? $this->info("Confirmed in block {$blockNumber}.")
            : $this->error("Transaction reverted on-chain (block {$blockNumber}).");
    }
}
