<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin notifications (DollarHub structure — controller + Blade, not Livewire).
 * Read-only operator alerts; mark-read and mark-all-read are form POST actions.
 */
class AdminNotificationController extends Controller
{
    public function index(): View
    {
        $admin = auth('admin')->user();

        return view('admin.notifications', [
            'notifications' => $admin->notifications()->latest()->paginate(20),
            'unread' => $admin->unreadNotifications()->count(),
        ]);
    }

    public function markRead(string $id): RedirectResponse
    {
        auth('admin')->user()->notifications()->where('id', $id)->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        auth('admin')->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked read.');
    }
}
