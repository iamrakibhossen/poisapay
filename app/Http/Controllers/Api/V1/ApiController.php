<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Base API controller enforcing the house conventions (TDD §8.1):
 * a consistent error envelope { error: { code, message, details } } and a
 * data envelope for success responses.
 */
abstract class ApiController extends Controller
{
    protected function ok(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json(array_filter([
            'data' => $data,
            'meta' => $meta ?: null,
        ], fn ($v) => ! is_null($v)), $status);
    }

    protected function fail(string $code, string $message, array $details = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details ?: null,
            ], fn ($v) => ! is_null($v)),
        ], $status);
    }

    /** Idempotency-Key header, required on mutating money endpoints (§8.1). */
    protected function idempotencyKey(): string
    {
        return request()->header('Idempotency-Key') ?: (string) Str::uuid();
    }
}
