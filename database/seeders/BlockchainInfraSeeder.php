<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Chain;
use App\Models\GasWallet;
use App\Models\RpcEndpoint;
use Illuminate\Database\Seeder;

/** Seed RPC endpoints (2 per chain, primary + failover) and funded gas wallets. */
class BlockchainInfraSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            'ethereum' => ['https://eth.llamarpc.test', 'https://rpc.ankr.test/eth'],
            'bsc' => ['https://bsc-dataseed.test', 'https://rpc.ankr.test/bsc'],
            'tron' => ['https://api.trongrid.test', 'https://tron.rpc.test'],
        ];

        foreach (Chain::all() as $chain) {
            $urls = $providers[$chain->key->value] ?? ['https://rpc.'.$chain->key->value.'.test'];
            foreach ($urls as $i => $url) {
                RpcEndpoint::updateOrCreate(
                    ['chain_id' => $chain->id, 'url' => $url],
                    [
                        'name' => ($i === 0 ? 'Primary' : 'Failover').' · '.$chain->name,
                        'priority' => $i + 1,
                        'weight' => $i === 0 ? 10 : 5,
                        'is_active' => true,
                        'status' => 'up',
                    ],
                );
            }

            GasWallet::updateOrCreate(
                ['chain_id' => $chain->id],
                [
                    'address' => $chain->is_evm ? '0xGAS'.strtoupper($chain->key->value) : 'TGAS'.strtoupper($chain->key->value),
                    'balance' => '5000000000000000000',   // 5 native coins
                    'min_threshold' => '500000000000000000', // 0.5 native
                    'is_active' => true,
                ],
            );
        }
    }
}
