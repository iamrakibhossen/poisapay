<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\RegisterUserAction;
use App\Domain\Auth\TwoFactorService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    public function register(Request $request, RegisterUserAction $action): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|unique:users,phone',
            'password' => 'required|string|min:8',
            'referral_code' => 'nullable|string',
        ]);

        $user = $action->execute($data);
        $token = $user->createToken('api')->plainTextToken;

        return $this->ok(['user' => $this->userPayload($user), 'token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return $this->fail('invalid_credentials', 'These credentials do not match our records.', [], 401);
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->ok(['challenge' => 'two_factor', 'user_id' => $user->id], 200);
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->ok(['user' => $this->userPayload($user), 'token' => $token]);
    }

    public function twoFactorVerify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|uuid',
            'code' => 'required|string',
        ]);

        $user = User::findOrFail($data['user_id']);
        if (! app(TwoFactorService::class)->verify($user, $data['code'])) {
            return $this->fail('invalid_2fa', 'Invalid authentication code.', [], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return $this->ok(['user' => $this->userPayload($user), 'token' => $token]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(['message' => 'Signed out.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'uid' => $user->uid,
            'kyc_tier' => $user->kyc_tier->value,
            'kyc_status' => $user->kyc_status->value,
            'base_currency' => $user->base_currency,
            'referral_code' => $user->referral_code,
        ];
    }
}
