<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Wallet\WalletService;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function index(Request $request, WalletService $wallets): JsonResponse
    {
        $balances = $wallets->walletsFor($request->user())
            ->map(fn ($w) => $w->toArray())
            ->values();

        return $this->ok($balances);
    }

    public function show(Request $request, string $symbol, WalletService $wallets): JsonResponse
    {
        $asset = Asset::where('symbol', $symbol)->where('is_active', true)->first();
        if (! $asset) {
            return $this->fail('asset_not_found', "Unknown asset [{$symbol}].", [], 404);
        }

        return $this->ok($wallets->balanceFor($request->user(), $asset)->toArray());
    }
}
