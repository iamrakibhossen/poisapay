<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Asset;
use App\Models\Chain;
use Illuminate\Http\JsonResponse;

class ReferenceController extends ApiController
{
    public function assets(): JsonResponse
    {
        $assets = Asset::with('chain')->where('is_active', true)->orderBy('sort')->get()
            ->map(fn (Asset $a) => [
                'id' => $a->id,
                'symbol' => $a->symbol,
                'name' => $a->name,
                'kind' => $a->kind->value,
                'decimals' => $a->decimals,
                'chain' => $a->chain?->key->value,
                'contract_address' => $a->contract_address,
                'is_stablecoin' => $a->is_stablecoin,
                'min_withdrawal' => $a->money($a->withdrawal_min)->toDecimal(),
                'withdrawal_fee' => $a->money($a->withdrawal_fee)->toDecimal(),
            ]);

        return $this->ok($assets);
    }

    public function chains(): JsonResponse
    {
        $chains = Chain::where('is_active', true)->get()
            ->map(fn (Chain $c) => [
                'key' => $c->key->value,
                'name' => $c->name,
                'native_symbol' => $c->native_symbol,
                'min_confirmations' => $c->min_confirmations,
                'is_evm' => $c->is_evm,
            ]);

        return $this->ok($chains);
    }
}
