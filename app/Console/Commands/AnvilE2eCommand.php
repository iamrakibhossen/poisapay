<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chain\Evm\AdvanceEvmDepositsAction;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\ScanEvmDepositsAction;
use App\Domain\Custody\AllocateDepositAddressAction;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\Evm\AdvanceEvmWithdrawalsAction;
use App\Domain\Withdrawal\Evm\EvmWithdrawalSigner;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\ChainType;
use App\Enums\DepositStatus;
use App\Enums\KycTier;
use App\Enums\WithdrawalStatus;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\CustodyXpub;
use App\Models\EvmNonce;
use App\Models\RpcEndpoint;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

/**
 * Local end-to-end custody smoke test against a running Foundry Anvil node.
 * Deploys nothing itself — pass a mock ERC-20 (6-decimals) address via --token.
 * Drives the REAL deposit watcher + withdrawal signer end to end: on-chain
 * transfer -> detect -> confirm -> credit, then request -> sign -> broadcast ->
 * settle, verifying on-chain balances via `cast`. Dev/local only.
 */
class AnvilE2eCommand extends Command
{
    protected $signature = 'paishapay:anvil-e2e
        {--token= : Mock ERC-20 contract address (omit to auto-deploy resources/anvil/MockUSDT)}
        {--rpc=http://127.0.0.1:8545 : Anvil JSON-RPC URL}
        {--deposit=25 : Deposit amount in USDT display units}
        {--withdraw=10 : Withdrawal amount in USDT display units}';

    protected $description = 'End-to-end deposit + withdraw against a local Anvil node';

    // Anvil deterministic dev accounts (public knowledge — local test node only).
    private const ACCT0_KEY = '0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';
    private const RECIPIENT = '0x90F79bf6EB2c4f870365E785982E1f101E93b906'; // anvil acct #3

    public function handle(): int
    {
        $rpc = (string) $this->option('rpc');
        $token = strtolower((string) $this->option('token'));
        if ($token === '') {
            $token = $this->deployMockToken($rpc);
            if ($token === null) {
                return self::FAILURE;
            }
        }

        $depositBase = (string) (int) round(((float) $this->option('deposit')) * 1_000_000);
        $withdrawBase = (string) (int) round(((float) $this->option('withdraw')) * 1_000_000);

        // 1. Go custody-live and point config at Anvil + the mock token (this process only).
        config([
            'poisapay.custody_simulated' => false,
            'providers.blockchain.driver' => 'http',
            'poisapay.custody.ethereum.rpc' => $rpc,
            'poisapay.custody.ethereum.chain_id' => 31337,
            'poisapay.custody.ethereum.usdt_contract' => $token,
            'poisapay.custody.ethereum.confirmations' => 1,
        ]);
        app()->forgetInstance(BlockchainProvider::class);
        $provider = app()->make(BlockchainProvider::class);

        // Relax address-book gates so a fresh destination can be paid in one shot.
        updateSetting('security_withdrawal_whitelist', false, 'security');
        updateSetting('security_address_cooldown', false, 'security');

        // 2. Seed chains/assets (idempotent) with the mock contract; register the REAL
        //    deposit xpub; drop any RPC endpoints so the provider uses the Anvil fallback.
        Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

        $chain = Chain::where('key', 'ethereum')->firstOrFail();
        RpcEndpoint::where('chain_id', $chain->id)->delete(); // fall back to config Anvil RPC

        $head = $provider->blockNumber(ChainType::Ethereum);
        $this->line("Anvil head block: <info>{$head}</info>  token: <info>{$token}</info>");

        // Anvil resets on restart: a call to a codeless address "succeeds" silently,
        // so verify the token is actually deployed on THIS chain before proceeding.
        $code = trim(Process::run(['cast', 'code', $token, '--rpc-url', $rpc])->output());
        if ($code === '' || $code === '0x') {
            $this->error("No contract code at {$token} on {$rpc} — Anvil was likely restarted. Redeploy the mock token (forge create) and pass the new address.");

            return self::FAILURE;
        }

        // RegistrySeeder keyed USDT-on-ethereum on the runtime-configured contract
        // (the mock token), so select that exact row — never rewrite contract_address,
        // which collides with rows left over from previous mock deployments.
        $asset = Asset::where('chain_id', $chain->id)->where('contract_address', $token)->firstOrFail();
        $asset->update(['min_confirmations' => 1, 'is_active' => true, 'deposit_enabled' => true]);
        Asset::where('chain_id', $chain->id)->where('symbol', 'USDT')
            ->whereKeyNot($asset->id)->update(['is_active' => false]); // retire stale mock rows

        $xpub = app()->make(SignerKeyProvider::class)->accountXpub(ChainType::Ethereum);
        $xpubRow = CustodyXpub::where('chain_id', $chain->id)->where('purpose', 'deposit')->firstOrFail();
        // Register the real xpub and park next_index past any addresses already derived
        // from it (RegistrySeeder / prior runs) so allocation never collides.
        $maxIndex = \App\Models\DepositAddress::where('xpub_id', $xpubRow->id)->max('derivation_index');
        $xpubRow->update(['xpub' => $xpub, 'is_active' => true, 'next_index' => $maxIndex === null ? 0 : $maxIndex + 1]);

        // 3. Test user + derived deposit address.
        $user = User::firstOrCreate(
            ['email' => 'anvil-e2e@example.test'],
            ['name' => 'Anvil E2E', 'password' => bcrypt('password'), 'kyc_tier' => KycTier::Full],
        );
        $user->update(['kyc_tier' => KycTier::Full, 'is_frozen' => false]);

        $depositAddr = app()->make(AllocateDepositAddressAction::class)->execute($user, $chain)->address;
        $this->line("Deposit address: <info>{$depositAddr}</info>");

        // 4. On-chain deposit: send ERC-20 from a funded Anvil account to the deposit address.
        $this->step('Sending on-chain deposit', [
            'cast', 'send', $token, 'transfer(address,uint256)', $depositAddr, $depositBase,
            '--private-key', self::ACCT0_KEY, '--rpc-url', $rpc,
        ]);
        $this->mine($rpc, 2);

        // 5. Detect + confirm + credit.
        $detected = app()->make(ScanEvmDepositsAction::class)->execute(ChainType::Ethereum);
        $this->line("Deposits detected: <info>{$detected}</info>");
        app()->make(AdvanceEvmDepositsAction::class)->execute(ChainType::Ethereum);

        $deposit = $user->deposits()->latest('id')->first();
        $ledger = app()->make(LedgerService::class);
        $balance = $ledger->availableBalance($user, $asset->id)->baseString();
        $this->line("Deposit status: <info>{$deposit?->status->value}</info>  ledger balance (base): <info>{$balance}</info>");

        if ($deposit?->status !== DepositStatus::Credited) {
            $this->error('Deposit did not credit — aborting before withdrawal.');

            return self::FAILURE;
        }

        // 6. Fund the hot wallet (ETH for gas + ERC-20 liquidity to pay out).
        $hot = app()->make(SignerKeyProvider::class)->hotWalletAddress(ChainType::Ethereum);
        $this->line("Hot wallet: <info>{$hot}</info>");

        // Fresh-chain hygiene: Anvil restarts reset on-chain nonces to 0 while the DB
        // reservation keeps counting, so a signed tx would queue forever on a future
        // nonce. Drop the reservation (NonceManager re-syncs from the chain) and fail
        // any broadcast withdrawals whose tx died with the previous session.
        EvmNonce::where('chain', 'ethereum')->where('address', strtolower($hot))->delete();
        Withdrawal::where('status', WithdrawalStatus::Broadcast)
            ->whereHas('asset', fn ($q) => $q->where('chain_id', $chain->id))
            ->with('onchainTx')->get()
            ->each(function (Withdrawal $w) use ($provider) {
                if ($w->onchainTx && $provider->getTransactionReceipt(ChainType::Ethereum, $w->onchainTx->tx_hash) === null) {
                    $w->update(['status' => WithdrawalStatus::Failed, 'failure_reason' => 'Stale broadcast from a previous Anvil session.']);
                    $this->warn("Failed stale broadcast withdrawal {$w->id} (tx gone after Anvil restart).");
                }
            });
        $this->step('Funding hot wallet gas (ETH)', [
            'cast', 'send', $hot, '--value', '1ether', '--private-key', self::ACCT0_KEY, '--rpc-url', $rpc,
        ]);
        $this->step('Funding hot wallet liquidity (USDT)', [
            'cast', 'send', $token, 'transfer(address,uint256)', $hot, '100000000',
            '--private-key', self::ACCT0_KEY, '--rpc-url', $rpc,
        ]);

        // 7. Request + approve + sign + broadcast + settle the withdrawal.
        $amount = $asset->money($withdrawBase);
        $withdrawal = app()->make(RequestWithdrawalAction::class)->execute(
            user: $user, asset: $asset, amount: $amount, toAddress: self::RECIPIENT,
            idempotencyKey: 'anvil-e2e:'.now()->getTimestampMs(),
        );
        if ($withdrawal->status !== WithdrawalStatus::Approved) {
            $original = $withdrawal->status->value;
            $withdrawal->update(['status' => WithdrawalStatus::Approved, 'requires_review' => false]);
            $this->warn("Withdrawal came back '{$original}' (risk/velocity); force-approved for the test.");
        }

        $recipientBefore = $this->balanceOf($rpc, $token, self::RECIPIENT);
        $withdrawal = app()->make(EvmWithdrawalSigner::class)->execute($withdrawal->fresh());
        $this->line("Withdrawal after broadcast: <info>{$withdrawal->status->value}</info>  tx: <info>".($withdrawal->onchainTx?->tx_hash ?? '—').'</info>');
        $this->mine($rpc, 2);
        app()->make(AdvanceEvmWithdrawalsAction::class)->execute(ChainType::Ethereum);

        $withdrawal->refresh();
        $recipientAfter = $this->balanceOf($rpc, $token, self::RECIPIENT);
        $this->line("Withdrawal final status: <info>{$withdrawal->status->value}</info>");
        $this->line("Recipient on-chain USDT: <info>{$recipientBefore}</info> -> <info>{$recipientAfter}</info>");

        $delta = bcsub($recipientAfter, $recipientBefore);
        $ok = $withdrawal->status === WithdrawalStatus::Completed && $delta === $withdrawBase;
        $this->newLine();
        $this->{$ok ? 'info' : 'error'}($ok
            ? "PASS — deposit credited and {$this->option('withdraw')} USDT withdrawn on-chain (delta {$delta})."
            : "FAIL — status {$withdrawal->status->value}, on-chain delta {$delta} (expected {$withdrawBase}).");

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Deploy resources/anvil/MockUSDT (pre-compiled creation bytecode) from Anvil
     * account #0, minting 1,000,000 USDT (6 decimals) to it. Returns the address.
     */
    private function deployMockToken(string $rpc): ?string
    {
        $bytecode = trim((string) file_get_contents(resource_path('anvil/MockUSDT.creation.hex')));
        // ABI-encode the uint256 constructor arg (supply = 1e12 base units).
        $supply = str_pad(dechex(1_000_000_000_000), 64, '0', STR_PAD_LEFT);

        $result = Process::run([
            'cast', 'send', '--json', '--private-key', self::ACCT0_KEY, '--rpc-url', $rpc,
            '--create', $bytecode.$supply,
        ]);
        $address = strtolower((string) (json_decode($result->output(), true)['contractAddress'] ?? ''));
        if (! $result->successful() || ! str_starts_with($address, '0x')) {
            $this->error('Mock token deploy failed: '.trim($result->errorOutput() ?: $result->output()));

            return null;
        }
        $this->line("Deployed MockUSDT: <info>{$address}</info>");

        return $address;
    }

    /** @param array<int, string> $cmd */
    private function step(string $label, array $cmd): void
    {
        $this->line("→ {$label}");
        $result = Process::run($cmd);
        if (! $result->successful()) {
            $this->warn(trim($result->errorOutput() ?: $result->output()));
        }
    }

    private function mine(string $rpc, int $blocks): void
    {
        Process::run(['cast', 'rpc', 'anvil_mine', (string) $blocks, '--rpc-url', $rpc]);
    }

    private function balanceOf(string $rpc, string $token, string $address): string
    {
        $out = trim(Process::run(['cast', 'call', $token, 'balanceOf(address)(uint256)', $address, '--rpc-url', $rpc])->output());

        // cast may render as "12345 [1.2e4]" — keep the leading integer.
        return preg_split('/\s+/', $out)[0] ?: '0';
    }
}
