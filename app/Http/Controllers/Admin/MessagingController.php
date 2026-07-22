<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Notification\BroadcastAnnouncementAction;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin messaging (DollarHub structure — controller + Blade, not Livewire).
 * Notification-template catalogue plus operator announcement broadcasts. The
 * modals are Alpine-only UI; both forms POST traditionally, and the same domain
 * action/model the old Livewire component used is invoked here.
 */
class MessagingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManage();

        $tab = $request->query('tab', 'templates') === 'announcements' ? 'announcements' : 'templates';

        $announcements = $tab === 'announcements'
            ? Announcement::with('sender')->latest('sent_at')->paginate(25)->withQueryString()
            : collect();

        return view('admin.messaging', [
            'tab' => $tab,
            'templates' => $tab === 'templates'
                ? NotificationTemplate::orderBy('key')->orderBy('locale')->get()
                : collect(),
            'announcements' => $announcements,
            'stats' => [
                'templates' => NotificationTemplate::count(),
                'activeTemplates' => NotificationTemplate::where('is_active', true)->count(),
                'announcements' => Announcement::count(),
                'recipients' => (int) Announcement::sum('recipients'),
            ],
        ]);
    }

    public function saveTemplate(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $request->merge(['is_active' => $request->boolean('is_active')]);

        $editingId = $request->input('id') ?: null;

        $unique = 'unique:notification_templates,key,'.($editingId ?? 'NULL').',id,locale,'.$request->input('locale');

        $data = $request->validate([
            'key' => 'required|string|max:120|'.$unique,
            'locale' => 'required|string|max:10',
            'name' => 'required|string|max:120',
            'category' => 'required|in:security,money,marketing,product',
            'channels' => 'array',
            'channels.*' => 'in:in_app,email',
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string',
            'is_active' => 'boolean',
        ]);

        try {
            $attributes = [
                'key' => $data['key'],
                'locale' => $data['locale'],
                'name' => $data['name'],
                'category' => $data['category'],
                'channels' => array_values($data['channels'] ?? []),
                'subject' => ($data['subject'] ?? null) ?: null,
                'body' => $data['body'],
                'is_active' => (bool) $data['is_active'],
            ];

            // Never pass id=null to create() on a HasUuids model (mass-assignment guard).
            $editingId
                ? tap(NotificationTemplate::whereKey($editingId)->firstOrFail())->update($attributes)
                : NotificationTemplate::create($attributes);

            return redirect()->route('admin.messaging')
                ->with('success', $editingId ? 'Template updated.' : 'Template created.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.messaging')
                ->with('error', 'Could not save the template: '.$e->getMessage());
        }
    }

    public function toggleTemplate(Request $request, string $id): RedirectResponse
    {
        $this->authorizeManage();

        try {
            $t = NotificationTemplate::findOrFail($id);
            $t->update(['is_active' => ! $t->is_active]);

            return redirect()->route('admin.messaging')
                ->with('success', $t->is_active ? 'Template activated.' : 'Template deactivated.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.messaging')
                ->with('error', 'Could not update the template: '.$e->getMessage());
        }
    }

    public function sendAnnouncement(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'annTitle' => 'required|string|max:160',
            'annBody' => 'required|string',
            'annSegment' => 'required|in:all,kyc_full,merchants',
            'annChannels' => 'required|array|min:1',
            'annChannels.*' => 'in:in_app,email',
            'annCategory' => 'required|in:security,money,marketing,product',
        ]);

        try {
            $announcement = app(BroadcastAnnouncementAction::class)->execute(
                auth('admin')->user(),
                $data['annTitle'],
                $data['annBody'],
                $data['annSegment'],
                array_values($data['annChannels']),
                $data['annCategory'],
            );

            return redirect()->route('admin.messaging', ['tab' => 'announcements'])
                ->with('success', 'Announcement sent to '.number_format($announcement->recipients).' recipient(s).');
        } catch (\Throwable $e) {
            return redirect()->route('admin.messaging', ['tab' => 'announcements'])
                ->with('error', 'Could not send the announcement: '.$e->getMessage());
        }
    }

    private function authorizeManage(): void
    {
        abort_unless(auth('admin')->user()?->can('manage-settings') || auth('admin')->user()?->hasRole('super-admin'), 403);
    }
}
