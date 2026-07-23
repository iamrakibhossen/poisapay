<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin notifications (DollarHub structure — controller + Blade, not Livewire).
 * Mirrors the consumer notification centre: a filterable, date-bucketed activity
 * feed where each row is a click target that marks-read then follows its deep
 * link. Categories map to the operator-relevant alert streams.
 */
class AdminNotificationController extends Controller
{
    /** Operator alert streams → display label, icon and tint (mirrors the frontend feed). */
    public const CATEGORY_META = [
        'security' => ['label' => 'Security', 'icon' => 'shield-exclamation', 'tint' => 'bg-rose-50 text-rose-600'],
        'compliance' => ['label' => 'Compliance', 'icon' => 'scale', 'tint' => 'bg-orange-50 text-orange-600'],
        'finance' => ['label' => 'Finance', 'icon' => 'banknotes', 'tint' => 'bg-emerald-50 text-emerald-600'],
        'deposit' => ['label' => 'Deposits', 'icon' => 'arrow-down-tray', 'tint' => 'bg-emerald-50 text-emerald-600'],
        'withdrawal' => ['label' => 'Withdrawals', 'icon' => 'arrow-up-tray', 'tint' => 'bg-amber-50 text-amber-600'],
        'kyc' => ['label' => 'KYC', 'icon' => 'identification', 'tint' => 'bg-sky-50 text-sky-600'],
        'card' => ['label' => 'Cards', 'icon' => 'credit-card', 'tint' => 'bg-indigo-50 text-indigo-600'],
        'merchant' => ['label' => 'Merchants', 'icon' => 'building-storefront', 'tint' => 'bg-blue-50 text-blue-600'],
        'support' => ['label' => 'Support', 'icon' => 'lifebuoy', 'tint' => 'bg-teal-50 text-teal-600'],
        'p2p' => ['label' => 'P2P', 'icon' => 'user-group', 'tint' => 'bg-violet-50 text-violet-600'],
        'invoice' => ['label' => 'Invoices', 'icon' => 'receipt-percent', 'tint' => 'bg-brand-50 text-brand-600'],
        'general' => ['label' => 'System', 'icon' => 'bell', 'tint' => 'bg-neutral-100 text-neutral-500'],
    ];

    public function index(Request $request): View
    {
        $admin = auth('admin')->user();

        $items = $admin->notifications()->latest()->limit(150)->get()->map(function ($note) {
            $data = (array) $note->data;
            $category = $data['category'] ?? 'general';

            return [
                'id' => $note->id,
                'category' => isset(self::CATEGORY_META[$category]) ? $category : 'general',
                'title' => $data['title'] ?? 'Notification',
                'body' => $data['body'] ?? null,
                'url' => $data['url'] ?? null,
                'is_unread' => $note->read_at === null,
                'at' => $note->created_at,
                'created' => $note->created_at?->diffForHumans(),
            ];
        });

        $unreadCount = $items->where('is_unread', true)->count();
        $categoryCounts = $items->countBy('category');

        // Filter: all | unread | <category>. Anything unknown falls back to all.
        $filter = (string) $request->query('filter', 'all');
        $visible = match (true) {
            $filter === 'unread' => $items->where('is_unread', true),
            isset(self::CATEGORY_META[$filter]) => $items->where('category', $filter),
            default => $items,
        };

        // Newest-first feed grouped into human date buckets (groupBy keeps order).
        $groups = $visible->groupBy(fn ($n) => $this->bucketFor($n['at']));

        return view('admin.notifications', [
            'groups' => $groups,
            'total' => $items->count(),
            'unreadCount' => $unreadCount,
            'categoryCounts' => $categoryCounts,
            'filter' => $filter,
            'categoryMeta' => self::CATEGORY_META,
        ]);
    }

    /** Bucket a timestamp into the label its activity group is filed under. */
    private function bucketFor(?Carbon $at): string
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
        $admin = auth('admin')->user();
        $notification = $admin->notifications()->where('id', $id)->first();
        $notification?->markAsRead();

        // Follow the alert's deep link (same-site only) after clearing it, else
        // land back on the feed.
        $url = $notification ? ((array) $notification->data)['url'] ?? null : null;
        if (is_string($url) && $url !== '' && $this->isLocalUrl($request, $url)) {
            return redirect()->to($url);
        }

        return redirect()->route('admin.notifications');
    }

    /** Whether a stored deep link is a same-site path or URL (never off-host). */
    private function isLocalUrl(Request $request, string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && $host === $request->getHost();
    }

    public function markAllRead(): RedirectResponse
    {
        auth('admin')->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked read.');
    }
}
