<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Security\AddressBookService;
use App\Http\Controllers\Controller;
use App\Models\AddressBookEntry;
use App\Models\UserPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * REST API for the user security centre (Wave 4): withdrawal-address whitelist,
 * login history, and security events. Bearer-authenticated (Sanctum), rate-limited
 * by the shared `api` limiter.
 */
class SecurityController extends Controller
{
    public function addresses(Request $request): JsonResponse
    {
        app(AddressBookService::class)->promoteMatured($request->user());

        return response()->json([
            'data' => $request->user()->addressBook()->get()->map(fn (AddressBookEntry $a) => [
                'id' => $a->id,
                'label' => $a->label,
                'address' => $a->address,
                'chain_id' => $a->chain_id,
                'status' => $a->status,
                'whitelisted' => $a->isWhitelisted(),
                'cooldown_until' => $a->cooldown_until?->toIso8601String(),
            ]),
        ]);
    }

    public function storeAddress(Request $request, AddressBookService $addresses): JsonResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'max:128'],
            'label' => ['nullable', 'string', 'max:64'],
            'chain_id' => ['nullable', 'integer', 'exists:chains,id'],
        ]);

        $entry = $addresses->add($request->user(), trim($data['address']), $data['label'] ?? null, $data['chain_id'] ?? null);

        return response()->json(['id' => $entry->id, 'status' => $entry->status], 201);
    }

    public function destroyAddress(Request $request, string $id): JsonResponse
    {
        AddressBookEntry::where('user_id', $request->user()->id)->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    public function events(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->securityEvents()->limit(50)->get()
                ->map(fn ($e) => [
                    'type' => $e->type,
                    'severity' => $e->severity,
                    'ip_address' => $e->ip_address,
                    'country' => $e->country,
                    'risk_score' => $e->risk_score,
                    'created_at' => $e->created_at->toIso8601String(),
                ]),
        ]);
    }

    /** Register a device push token (FCM/APNs) for the authenticated user. */
    public function registerPushToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'in:web,ios,android'],
        ]);

        UserPushToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $data['token']],
            ['platform' => $data['platform'] ?? 'web', 'last_used_at' => now()],
        );

        return response()->json(['ok' => true], 201);
    }

    public function deletePushToken(Request $request): JsonResponse
    {
        UserPushToken::where('user_id', $request->user()->id)
            ->where('token', (string) $request->input('token'))
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function loginHistory(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->loginHistories()->limit(50)->get()
                ->map(fn ($l) => [
                    'ip_address' => $l->ip_address,
                    'country' => $l->country,
                    'new_device' => $l->new_device,
                    'risk_score' => $l->risk_score,
                    'created_at' => $l->created_at->toIso8601String(),
                ]),
        ]);
    }
}
