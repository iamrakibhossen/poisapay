<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Security\AddressBookService;
use App\Http\Controllers\Controller;
use App\Models\AddressBookEntry;
use App\Models\SecurityEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * User security actions (Wave 4): withdrawal-address whitelist management,
 * anti-phishing code, security-event acknowledgement and session controls. The
 * page itself now lives under Settings › Security; these endpoints handle its
 * form POSTs and redirect back with a flash message.
 */
class SecurityController extends Controller
{
    public function addAddress(Request $request, AddressBookService $addresses): RedirectResponse
    {
        $data = $request->validate([
            'address' => ['required', 'string', 'max:128'],
            'label' => ['nullable', 'string', 'max:64'],
            'chain_id' => ['nullable', 'integer', 'exists:chains,id'],
        ]);

        $addresses->add(
            $request->user(),
            trim($data['address']),
            $data['label'] ?? null,
            $data['chain_id'] ?? null,
        );

        return back()->with('status', 'Address added. It enters a security cooldown before it can be used for withdrawals.');
    }

    public function deleteAddress(Request $request, string $id): RedirectResponse
    {
        $entry = AddressBookEntry::where('user_id', $request->user()->id)->findOrFail($id);
        $entry->delete();
        ActivityLogger::log('security.address.removed', null, ['address' => $entry->address], actor: $request->user());

        return back()->with('status', 'Address removed.');
    }

    public function saveAntiPhishing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anti_phishing_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9 _-]*$/'],
        ]);

        $request->user()->update(['anti_phishing_code' => $data['anti_phishing_code'] ?: null]);
        ActivityLogger::log('security.anti_phishing.updated', null, [], actor: $request->user());

        return back()->with('status', 'Anti-phishing code saved. It will appear in genuine emails from us.');
    }

    public function acknowledgeEvent(Request $request, string $id): RedirectResponse
    {
        SecurityEvent::where('user_id', $request->user()->id)->where('id', $id)
            ->update(['acknowledged_at' => now()]);

        return back()->with('status', 'Marked as reviewed.');
    }

    /** Invalidate every OTHER active session for this user (keeps the current one). */
    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        ActivityLogger::log('security.sessions.logout_others', null, [], actor: $request->user());

        return back()->with('status', 'All other sessions have been signed out.');
    }
}
