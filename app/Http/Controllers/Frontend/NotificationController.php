<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Notification centre — server-rendered. The controller loads the activity feed,
 * unread count and delivery preferences and passes them to the Blade view; the
 * mark-read and preference actions are plain form POSTs that redirect back with a
 * flash message (or validation errors).
 */
class NotificationController extends Controller
{
    /** Categories exposed in the preferences matrix, with display labels. */
    public const CATEGORIES = [
        'security' => 'Security',
        'money' => 'Money',
        'product' => 'Product',
        'marketing' => 'Marketing',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = $user->notifications()->latest()->limit(100)->get()->map(function ($note) {
            $data = (array) $note->data;

            return [
                'id' => $note->id,
                'category' => $data['category'] ?? 'product',
                'title' => $data['title'] ?? 'Notification',
                'body' => $data['body'] ?? null,
                'url' => $data['url'] ?? null,
                'is_unread' => $note->read_at === null,
                'created' => $note->created_at?->diffForHumans(),
            ];
        });

        return view('frontend.notifications', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'prefs' => $this->preferencesFor($request),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->route('notifications');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route('notifications')->with('success', 'All notifications marked as read.');
    }

    public function savePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prefs' => ['required', 'array'],
            'prefs.*' => ['array'],
            'prefs.*.in_app' => ['boolean'],
            'prefs.*.email' => ['boolean'],
            'prefs.*.sms' => ['boolean'],
            'prefs.*.push' => ['boolean'],
        ]);

        $incoming = $validated['prefs'];
        $uid = $request->user()->id;

        // Only the known categories may be persisted.
        if (array_diff(array_keys($incoming), array_keys(self::CATEGORIES)) !== []) {
            throw ValidationException::withMessages([
                'prefs' => 'Unknown notification category.',
            ]);
        }

        foreach (array_keys(self::CATEGORIES) as $cat) {
            $row = $incoming[$cat] ?? [];

            // Force security channels to always-on regardless of client state.
            if ($cat === 'security') {
                $row['in_app'] = true;
                $row['email'] = true;
            }

            NotificationPreference::updateOrCreate(
                ['user_id' => $uid, 'category' => $cat],
                [
                    'in_app' => (bool) ($row['in_app'] ?? false),
                    'email' => (bool) ($row['email'] ?? false),
                    'sms' => (bool) ($row['sms'] ?? false),
                    'push' => (bool) ($row['push'] ?? false),
                ]
            );
        }

        return redirect()->route('notifications')->with('success', 'Preferences saved.');
    }

    /**
     * Build the preference matrix for the user, mirroring the Livewire mount():
     * sensible defaults for unseen categories and mandatory security channels.
     *
     * @return array<string, array{in_app: bool, email: bool, sms: bool, push: bool}>
     */
    private function preferencesFor(Request $request): array
    {
        $uid = $request->user()->id;
        $prefs = [];

        foreach (array_keys(self::CATEGORIES) as $cat) {
            $pref = NotificationPreference::firstOrNew([
                'user_id' => $uid,
                'category' => $cat,
            ]);

            $prefs[$cat] = [
                'in_app' => $pref->exists ? (bool) $pref->in_app : true,
                'email' => $pref->exists ? (bool) $pref->email : true,
                'sms' => $pref->exists ? (bool) $pref->sms : false,
                'push' => $pref->exists ? (bool) $pref->push : true,
            ];
        }

        // Security notifications are mandatory across in-app + email.
        $prefs['security']['in_app'] = true;
        $prefs['security']['email'] = true;

        return $prefs;
    }
}
