<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Carbon\CarbonInterface;
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
    /**
     * Notification categories with their display label plus the icon + tint used
     * to render them in the activity feed. Also the source of truth for the
     * preferences matrix and its validation.
     */
    public const CATEGORY_META = [
        'security' => ['label' => 'Security', 'icon' => 'shield-check', 'tint' => 'bg-red-50 text-red-600'],
        'money' => ['label' => 'Money', 'icon' => 'banknotes', 'tint' => 'bg-emerald-50 text-emerald-600'],
        'product' => ['label' => 'Product', 'icon' => 'sparkles', 'tint' => 'bg-blue-50 text-blue-600'],
        'marketing' => ['label' => 'Marketing', 'icon' => 'megaphone', 'tint' => 'bg-indigo-50 text-indigo-600'],
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        // Filter: all | unread | <category>. Anything unknown falls back to all.
        $filter = (string) $request->query('filter', 'all');

        $query = $user->notifications()->latest();
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif (isset(self::CATEGORY_META[$filter])) {
            // `data` is a text column, so cast to jsonb to read the category key.
            $query->whereRaw("(data::jsonb) ->> 'category' = ?", [$filter]);
        }

        $paginator = $query->paginate(20)->withQueryString();

        $items = $paginator->getCollection()->map(function ($note) {
            $data = (array) $note->data;
            $category = $data['category'] ?? 'product';

            return [
                'id' => $note->id,
                'category' => isset(self::CATEGORY_META[$category]) ? $category : 'product',
                'title' => $data['title'] ?? 'Notification',
                'body' => $data['body'] ?? null,
                'url' => $data['url'] ?? null,
                'is_unread' => $note->read_at === null,
                'at' => $note->created_at,
                'created' => $note->created_at?->diffForHumans(),
            ];
        });

        // Full-set counts for the filter chips (independent of the current page/filter).
        $total = $user->notifications()->count();
        $unreadCount = $user->notifications()->whereNull('read_at')->count();
        $categoryCounts = $user->notifications()
            ->reorder()   // drop the relation's default created_at ordering (invalid with GROUP BY)
            ->selectRaw("(data::jsonb) ->> 'category' as cat, count(*) as c")
            ->groupBy('cat')
            ->pluck('c', 'cat');

        // Group the current page (already newest-first) into human date buckets. groupBy
        // preserves insertion order, so the buckets stay chronological.
        $groups = $items->groupBy(fn ($n) => $this->bucketFor($n['at']));

        return view('frontend.notifications', [
            'groups' => $groups,
            'paginator' => $paginator,
            'total' => $total,
            'unreadCount' => $unreadCount,
            'categoryCounts' => $categoryCounts,
            'filter' => $filter,
            'categoryMeta' => self::CATEGORY_META,
        ]);
    }

    /** Standalone delivery-preferences page. */
    public function preferences(Request $request): View
    {
        return view('frontend.notification-preferences', [
            'prefs' => $this->preferencesFor($request),
            'categories' => array_map(fn ($m) => $m['label'], self::CATEGORY_META),
            'categoryMeta' => self::CATEGORY_META,
        ]);
    }

    /** Bucket a timestamp into the label its activity group is filed under. */
    private function bucketFor(?CarbonInterface $at): string
    {
        return match (true) {
            $at === null => 'Earlier',
            $at->isToday() => 'Today',
            $at->isYesterday() => 'Yesterday',
            $at->greaterThan(now()->subDays(7)) => 'Earlier this week',
            default => 'Earlier',
        };
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        // Clicking a notification clears it from the unread count and then follows
        // its deep link (if any); otherwise it lands back on the feed. Only same-site
        // links are honoured to avoid an open redirect off arbitrary payload data.
        $url = ((array) $notification->data)['url'] ?? null;
        if (is_string($url) && $url !== '' && $this->isLocalUrl($request, $url)) {
            return redirect()->to($url);
        }

        return redirect()->route('notifications.index');
    }

    /** Whether a stored deep link is a same-site path or URL (never off-host). */
    private function isLocalUrl(Request $request, string $url): bool
    {
        // A leading single slash is a relative path; reject protocol-relative "//host".
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && $host === $request->getHost();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->route('notifications.index')->with('success', 'All notifications marked as read.');
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
        if (array_diff(array_keys($incoming), array_keys(self::CATEGORY_META)) !== []) {
            throw ValidationException::withMessages([
                'prefs' => 'Unknown notification category.',
            ]);
        }

        foreach (array_keys(self::CATEGORY_META) as $cat) {
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

        return redirect()->route('notifications.preferences')->with('success', 'Preferences saved.');
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

        foreach (array_keys(self::CATEGORY_META) as $cat) {
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
