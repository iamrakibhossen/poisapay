<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ledger\AccountResolver;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Currency;
use App\Models\CustodyXpub;
use App\Support\Money;
use Illuminate\Database\Seeder;

/** Launch chains + currencies + per-chain assets + deposit xpubs (TDD §2.4 launch assets). */
class RegistrySeeder extends Seeder
{
    public function run(): void
    {
        $chains = [
            ['key' => 'tron', 'name' => 'Tron', 'native_symbol' => 'TRX', 'min_confirmations' => 19, 'is_evm' => false],
            ['key' => 'ethereum', 'name' => 'Ethereum', 'native_symbol' => 'ETH', 'min_confirmations' => 12, 'is_evm' => true],
            ['key' => 'bsc', 'name' => 'BNB Smart Chain', 'native_symbol' => 'BNB', 'min_confirmations' => 15, 'is_evm' => true],
            ['key' => 'polygon', 'name' => 'Polygon', 'native_symbol' => 'POL', 'min_confirmations' => 30, 'is_evm' => true],
            ['key' => 'arbitrum', 'name' => 'Arbitrum One', 'native_symbol' => 'ETH', 'min_confirmations' => 20, 'is_evm' => true],
            ['key' => 'optimism', 'name' => 'Optimism', 'native_symbol' => 'ETH', 'min_confirmations' => 20, 'is_evm' => true],
            ['key' => 'base', 'name' => 'Base', 'native_symbol' => 'ETH', 'min_confirmations' => 20, 'is_evm' => true],
            ['key' => 'avalanche', 'name' => 'Avalanche C-Chain', 'native_symbol' => 'AVAX', 'min_confirmations' => 15, 'is_evm' => true],
        ];

        // Stablecoins deployed on many chains (one currency, many networks).
        $usdt = Currency::updateOrCreate(
            ['symbol' => 'USDT'],
            ['name' => 'Tether USD', 'kind' => 'crypto', 'is_stablecoin' => true, 'sort' => 1, 'is_active' => true],
        );
        $usdc = Currency::updateOrCreate(
            ['symbol' => 'USDC'],
            ['name' => 'USD Coin', 'kind' => 'crypto', 'is_stablecoin' => true, 'sort' => 2, 'is_active' => true],
        );

        foreach ($chains as $data) {
            $chain = Chain::updateOrCreate(['key' => $data['key']], $data);

            // Native coin — its own currency (TRX / ETH / BNB), one network each.
            $nativeCurrency = Currency::updateOrCreate(
                ['symbol' => $data['native_symbol']],
                ['name' => $data['name'].' Coin', 'kind' => 'crypto', 'sort' => 10, 'is_active' => true],
            );

            $native = Asset::updateOrCreate(
                ['chain_id' => $chain->id, 'contract_address' => null],
                [
                    'currency_id' => $nativeCurrency->id,
                    'symbol' => $data['native_symbol'],
                    'name' => $data['name'].' Coin',
                    'kind' => 'crypto',
                    'decimals' => 18,
                    'withdrawal_min' => $this->wholeToBase('0.001', 18),
                    'withdrawal_fee' => $this->wholeToBase('0.0005', 18),
                    'is_active' => true,
                    'sort' => 10,
                ],
            );

            // Stablecoin tokens on each chain. Ledger precision is a UNIFORM 6 decimals
            // so a coin pools cleanly across networks (BSC's 18-decimal on-chain amount
            // is normalised at the watcher/signer). The contract MUST match
            // config/poisapay.php custody.<chain>.{usdt,usdc}_contract so the watcher
            // resolves the asset. A blank contract (e.g. USDT on Base, USDC on Tron) is skipped.
            $tokens = [
                ['currency' => $usdt, 'symbol' => 'USDT', 'name' => 'Tether USD', 'sort' => 1,
                    'contract' => config("poisapay.custody.{$data['key']}.usdt_contract", $data['key'] === 'tron' ? 'USDT_TRON' : '')],
                ['currency' => $usdc, 'symbol' => 'USDC', 'name' => 'USD Coin', 'sort' => 2,
                    'contract' => config("poisapay.custody.{$data['key']}.usdc_contract", '')],
            ];
            foreach ($tokens as $token) {
                $contract = (string) $token['contract'];
                if ($contract === '') {
                    continue; // token not deployed / not offered on this chain
                }
                Asset::updateOrCreate(
                    ['chain_id' => $chain->id, 'contract_address' => $contract],
                    [
                        'currency_id' => $token['currency']->id,
                        'symbol' => $token['symbol'],
                        'name' => $token['name'],
                        'kind' => 'crypto',
                        'decimals' => 6,
                        'is_stablecoin' => true,
                        'withdrawal_min' => '1000000',   // 1.00
                        'withdrawal_fee' => '500000',    // 0.50
                        'is_active' => true,
                        'deposit_enabled' => true,
                        'sort' => $token['sort'],
                    ],
                );
            }

            // Register a deposit xpub per chain (public key material only, D4).
            CustodyXpub::updateOrCreate(
                ['chain_id' => $chain->id, 'purpose' => 'deposit'],
                [
                    'label' => $data['name'].' deposits',
                    'xpub' => 'xpub-'.$data['key'].'-'.hash('crc32', $data['key']),
                    'derivation_path' => $chain->is_evm ? "m/44'/60'/0'/0" : "m/44'/195'/0'/0",
                    'next_index' => 0,
                    'is_active' => true,
                ],
            );

            $native->refresh();
        }

        // Fiat currencies (§F1): BDT + USD — one "network" each (chain-less).
        $bdt = Currency::updateOrCreate(
            ['symbol' => 'BDT'],
            ['name' => 'Bangladeshi Taka', 'kind' => 'fiat', 'sort' => 2, 'is_active' => true],
        );
        Asset::updateOrCreate(
            ['symbol' => 'BDT', 'chain_id' => null, 'contract_address' => null],
            ['currency_id' => $bdt->id, 'name' => 'Bangladeshi Taka', 'kind' => 'fiat', 'currency_code' => 'BDT', 'decimals' => 2, 'is_active' => true, 'sort' => 2],
        );

        $usd = Currency::updateOrCreate(
            ['symbol' => 'USD'],
            ['name' => 'US Dollar', 'kind' => 'fiat', 'sort' => 3, 'is_active' => true],
        );
        Asset::updateOrCreate(
            ['symbol' => 'USD', 'chain_id' => null, 'contract_address' => 'FIAT_USD'],
            ['currency_id' => $usd->id, 'name' => 'US Dollar', 'kind' => 'fiat', 'currency_code' => 'USD', 'decimals' => 2, 'is_active' => true, 'sort' => 3],
        );

        // Provision system ledger accounts for every asset.
        $resolver = app(AccountResolver::class);
        Asset::all()->each(fn (Asset $asset) => $resolver->ensureSystemAccounts($asset->id));
    }

    private function wholeToBase(string $whole, int $decimals): string
    {
        return Money::ofDecimal($whole, $decimals)->baseString();
    }
}
