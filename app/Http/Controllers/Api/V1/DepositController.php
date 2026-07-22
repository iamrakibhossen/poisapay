<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Custody\AllocateDepositAddressAction;
use App\Models\Chain;
use App\Models\Deposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends ApiController
{
    public function createAddress(Request $request, AllocateDepositAddressAction $action): JsonResponse
    {
        $data = $request->validate(['chain' => 'required|string']);

        $chain = Chain::where('key', $data['chain'])->where('is_active', true)->first();
        if (! $chain) {
            return $this->fail('chain_not_found', "Unknown chain [{$data['chain']}].", [], 404);
        }

        $address = $action->execute($request->user(), $chain);

        return $this->ok([
            'chain' => $chain->key->value,
            'address' => $address->address,
            'min_confirmations' => $chain->min_confirmations,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $deposits = Deposit::with('asset')->where('user_id', $request->user()->id)
            ->latest()->limit(50)->get()
            ->map(fn (Deposit $d) => [
                'id' => $d->id,
                'asset' => $d->asset->symbol,
                'amount' => $d->money()->toDecimal(),
                'status' => $d->status->value,
                'confirmations' => $d->confirmations.'/'.$d->required_confirmations,
                'created_at' => $d->created_at->toIso8601String(),
            ]);

        return $this->ok($deposits);
    }
}
