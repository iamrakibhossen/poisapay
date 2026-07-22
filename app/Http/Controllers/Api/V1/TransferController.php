<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Transfer\ExecuteTransferAction;
use App\Models\Asset;
use App\Models\Transfer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransferController extends ApiController
{
    public function store(Request $request, ExecuteTransferAction $action): JsonResponse
    {
        $data = $request->validate([
            'recipient' => 'required|string',   // handle | email | phone
            'asset' => 'required|string',
            'amount' => 'required|numeric|gt:0',
            'memo' => 'nullable|string|max:140',
        ]);

        $asset = Asset::where('symbol', $data['asset'])->where('is_active', true)->first();
        if (! $asset) {
            return $this->fail('asset_not_found', 'Unknown asset.', [], 404);
        }

        $recipient = User::where('handle', $data['recipient'])
            ->orWhere('email', $data['recipient'])
            ->orWhere('phone', $data['recipient'])
            ->first();
        if (! $recipient) {
            return $this->fail('recipient_not_found', 'No PoisaPay user matches that handle.', [], 404);
        }

        try {
            $transfer = $action->execute(
                $request->user(),
                $recipient,
                $asset,
                Money::ofDecimal($data['amount'], $asset->decimals, $asset->symbol),
                $this->idempotencyKey(),
                $data['memo'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return $this->fail('transfer_failed', $e->getMessage(), [], 422);
        }

        return $this->ok([
            'id' => $transfer->id,
            'asset' => $asset->symbol,
            'amount' => $transfer->money()->toDecimal(),
            'status' => $transfer->status->value,
            'recipient' => $recipient->handle ?? $recipient->name,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $transfers = Transfer::with('asset')
            ->where('sender_id', $userId)->orWhere('recipient_id', $userId)
            ->latest()->limit(50)->get()
            ->map(fn (Transfer $t) => [
                'id' => $t->id,
                'direction' => $t->sender_id === $userId ? 'out' : 'in',
                'asset' => $t->asset->symbol,
                'amount' => $t->money()->toDecimal(),
                'kind' => $t->kind->value,
                'status' => $t->status->value,
                'created_at' => $t->created_at->toIso8601String(),
            ]);

        return $this->ok($transfers);
    }
}
