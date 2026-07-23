<?php

declare(strict_types=1);

namespace App\Domain\Chain\Evm;

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Enums\ChainType;

/**
 * EIP-1559 gas pricing (Wave 2). Suggests a priority tip and a max fee with base-fee
 * headroom (maxFee = 2·baseFee + tip) so a transaction remains valid across a couple
 * of base-fee bumps. Gas limit comes from config (a fixed ERC-20 transfer budget);
 * callers may override with a live eth_estimateGas.
 *
 * @phpstan-type GasParams array{maxPriorityFeePerGas: string, maxFeePerGas: string, gasLimit: string}
 */
class GasEstimationService
{
    public function __construct(private readonly BlockchainProvider $chain) {}

    /** @return array{maxPriorityFeePerGas: string, maxFeePerGas: string, gasLimit: string} */
    public function suggest(ChainType $chainType): array
    {
        $tip = $this->chain->maxPriorityFeePerGas($chainType);
        $base = $this->chain->baseFeePerGas($chainType);

        // maxFee = base*2 + tip (2 base-fee bumps of headroom).
        $maxFee = bcadd(bcmul($base === '' ? '0' : $base, '2'), $tip === '' ? '0' : $tip);

        return [
            'maxPriorityFeePerGas' => $tip === '' ? '0' : $tip,
            'maxFeePerGas' => $maxFee,
            'gasLimit' => (string) config('poisapay.custody.evm_transfer_gas', 90000),
        ];
    }
}
