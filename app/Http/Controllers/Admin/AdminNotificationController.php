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

    /** Postgres expression that pulls the stored category out of the JSON `data` text column. */
    private const CATEGORY_EXPR = "coalesce(nullif(data::jsonb->>'category', ''), 'general')";

    public function index(Request $request): View
    {
        $admin = auth('admin')->user();

        // Chip counts are computed across the operator's whole history (not the
        // current page) so the badges stay accurate as you page through.
        $total = $admin->notifications()->count();
        $unreadCount = $admin->unreadNotifications()->count();
        $categoryCounts = $this->categoryCounts($admin);

        // Filter: all | unread | <category>. Anything unknown falls back to all.
        $filter = (string) $request->query('filter', 'all');

        $query = $admin->notifications();
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif (isset(self::CATEGORY_META[$filter])) {
            $this->scopeToCategory($query, $filter);
        }

        $page = $query->paginate(25)->withQueryString();

        $items = collect($page->items())->map(function ($note) {
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

        // Newest-first feed grouped into human date buckets (groupBy keeps order).
        $groups = $items->groupBy(fn ($n) => $this->bucketFor($n['at']));

        return view('admin.notifications', [
            'groups' => $groups,
            'paginator' => $page,
            'total' => $total,
            'unreadCount' => $unreadCount,
            'categoryCounts' => $categoryCounts,
            'filter' => $filter,
            'categoryMeta' => self::CATEGORY_META,
        ]);
    }

    /** Per-category totals across all of the admin's notifications, folding unknown categories into `general`. */
    private function categoryCounts($admin): \Illuminate\Support\Collection
    {
        $counts = collect();

        $admin->notifications()
            ->reorder() // drop the relation's default `order by created_at` — illegal alongside group by
            ->selectRaw(self::CATEGORY_EXPR.' as category, count(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category')
            ->each(function ($aggregate, $category) use ($counts) {
                $key = isset(self::CATEGORY_META[$category]) ? $category : 'general';
                $counts[$key] = ($counts[$key] ?? 0) + (int) $aggregate;
            });

        return $counts;
    }

    /** Constrain a notifications query to one display category (folding unknown values into `general`). */
    private function scopeToCategory($query, string $filter): void
    {
        if ($filter === 'general') {
            // "general" is the catch-all: explicit general, empty, or any category we don't recognise.
            $known = array_values(array_diff(array_keys(self::CATEGORY_META), ['general']));
            $query->whereRaw(self::CATEGORY_EXPR.' not in ('.implode(',', array_fill(0, count($known), '?')).')', $known);

            return;
        }

        $query->whereRaw(self::CATEGORY_EXPR.' = ?', [$filter]);
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
