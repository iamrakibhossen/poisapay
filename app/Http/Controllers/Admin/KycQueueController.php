<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Kyc\ReviewKycAction;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Models\KycProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin KYC review queue (DollarHub structure — controller + Blade, not Livewire).
 * Approving/rejecting gates money-movement access via {@see ReviewKycAction}.
 */
class KycQueueController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth('admin')->user()->can('review-kyc') || auth('admin')->user()->hasRole('super-admin'), 403);

        $status = (string) $request->query('status', 'pending');

        $profiles = KycProfile::with('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.kyc-queue', [
            'profiles' => $profiles,
            'status' => $status,
            'tabs' => [
                'pending' => KycProfile::where('status', KycStatus::Pending->value)->count(),
                'approved' => KycProfile::where('status', KycStatus::Approved->value)->count(),
                'rejected' => KycProfile::where('status', KycStatus::Rejected->value)->count(),
                'all' => KycProfile::count(),
            ],
        ]);
    }

    /** Full review page for one submission (details + document images). */
    public function show(string $id): View
    {
        abort_unless(auth('admin')->user()->can('review-kyc') || auth('admin')->user()->hasRole('super-admin'), 403);

        $profile = KycProfile::with(['user', 'reviewedBy'])->findOrFail($id);

        return view('admin.kyc.show', ['profile' => $profile]);
    }

    /** Stream a private KYC document image for the reviewer (front|back|selfie). */
    public function file(string $id, string $slot): StreamedResponse
    {
        abort_unless(auth('admin')->user()->can('review-kyc') || auth('admin')->user()->hasRole('super-admin'), 403);
        abort_unless(in_array($slot, ['front', 'back', 'selfie'], true), 404);

        $profile = KycProfile::findOrFail($id);
        $path = $profile->documentPath($slot);

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('approve-kyc') || auth('admin')->user()->hasRole('super-admin'), 403);

        $profile = KycProfile::findOrFail($id);
        app(ReviewKycAction::class)->approve($profile, auth('admin')->user());

        return back()->with('success', "Approved {$profile->user->name} to {$profile->requested_tier->label()}.");
    }

    public function reject(Request $request, string $id): RedirectResponse
    {
        abort_unless(auth('admin')->user()->can('approve-kyc') || auth('admin')->user()->hasRole('super-admin'), 403);

        $data = $request->validate(['rejectReason' => 'required|string|min:3|max:255']);

        $profile = KycProfile::findOrFail($id);
        app(ReviewKycAction::class)->reject($profile, auth('admin')->user(), $data['rejectReason']);

        return back()->with('success', 'Verification rejected.');
    }
}
