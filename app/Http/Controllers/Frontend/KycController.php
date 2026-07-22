<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Kyc\SubmitKycAction;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * KYC / identity verification — server-rendered. The verification UI now lives in
 * the Settings "Verification" tab; {@see index()} redirects there. {@see submit()}
 * accepts the multipart application (personal details + document images) and hands
 * it to {@see SubmitKycAction}, redirecting back with a flash message (or errors).
 */
class KycController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('settings', ['tab' => 'verification']);
    }

    public function submit(Request $request, SubmitKycAction $action): RedirectResponse
    {
        $user = $request->user();

        if (! in_array($user->kyc_status, [KycStatus::None, KycStatus::Rejected], true)) {
            throw ValidationException::withMessages(['documentType' => 'A verification request is already in progress.']);
        }

        $validated = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'dateOfBirth' => ['required', 'date'],
            'country' => ['required', 'string', 'size:2'],
            'address' => ['required', 'string', 'max:500'],
            'documentType' => ['required', 'in:nid,passport'],
            'documentNumber' => ['required', 'string', 'max:64'],
            'documentFront' => ['required', 'image', 'max:5120'],
            'documentBack' => ['nullable', 'image', 'max:5120'],
            'selfie' => ['required', 'image', 'max:5120'],
        ]);

        $paths = [];
        $paths['front'] = $request->file('documentFront')->store('kyc/'.$user->id, 'local');
        if ($request->hasFile('documentBack')) {
            $paths['back'] = $request->file('documentBack')->store('kyc/'.$user->id, 'local');
        }
        $paths['selfie'] = $request->file('selfie')->store('kyc/'.$user->id, 'local');

        $action->execute($user, [
            'requested_tier' => KycTier::Full->value,
            'document_type' => $validated['documentType'],
            'document_number' => $validated['documentNumber'],
            'full_name' => $validated['fullName'],
            'date_of_birth' => $validated['dateOfBirth'],
            'country' => $validated['country'],
            'address' => $validated['address'],
            'document_paths' => $paths, // keyed: front / back / selfie
        ]);

        return redirect()->route('settings', ['tab' => 'verification'])->with('success', 'Verification submitted. We\'ll review it shortly.');
    }
}
